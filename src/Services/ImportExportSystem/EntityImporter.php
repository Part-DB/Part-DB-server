<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\ImportExportSystem;

use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Repository\StructuralDBElementRepository;
use App\Serializer\APIPlatform\SkippableItemNormalizer;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use function count;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use function is_array;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @see \App\Tests\Services\ImportExportSystem\EntityImporterTest
 */
class EntityImporter
{

    /**
     * The encodings that are supported by the importer, and that should be autodeceted.
     */
    private const ENCODINGS = ["ASCII", "UTF-8", "ISO-8859-1", "ISO-8859-15", "Windows-1252", "UTF-16", "UTF-32"];

    public function __construct(protected SerializerInterface $serializer, protected EntityManagerInterface $em, protected ValidatorInterface $validator)
    {
    }

    /**
     * Creates many entries at once, based on a (text) list of name.
     * The created entities are not persisted to database yet, so you have to do it yourself.
     *
     * @template T of AbstractNamedDBElement
     * @param string                           $lines      The list of names seperated by \n
     * @param string                           $class_name The name of the class for which the entities should be created
     * @phpstan-param class-string<T> $class_name
     * @param AbstractStructuralDBElement|null $parent     the element which will be used as parent element for new elements
     * @param array                            $errors     an associative array containing all validation errors
     * @param-out  list<array{'entity': object, 'violations': ConstraintViolationListInterface}>  $errors
     *
     * @return AbstractNamedDBElement[] An array containing all valid imported entities (with the type $class_name)
     * @return T[]
     */
    public function massCreation(string $lines, string $class_name, ?AbstractStructuralDBElement $parent = null, array &$errors = []): array
    {
        //Try to detect the text encoding of the data and convert it to UTF-8
        $lines = mb_convert_encoding($lines, 'UTF-8', mb_detect_encoding($lines, self::ENCODINGS));

        //Expand every line to a single entry:
        $names = explode("\n", $lines);

        if (!is_a($class_name, AbstractNamedDBElement::class, true)) {
            throw new InvalidArgumentException('$class_name must be a StructuralDBElement type!');
        }
        if ($parent instanceof AbstractStructuralDBElement && !$parent instanceof $class_name) {
            throw new InvalidArgumentException('$parent must have the same type as specified in $class_name!');
        }

        //Ensure that parent is already persisted. Otherwise the getNewEntityFromPath function will not work.
        if ($parent !== null && $parent->getID() === null) {
            throw new InvalidArgumentException('The parent must persisted to database!');
        }

        $repo = $this->em->getRepository($class_name);

        $errors = [];
        $valid_entities = [];

        $current_parent = $parent;
        $last_element = $parent;
        //We use this array to store all levels of indentation as a stack.
        $indentations = [0];

        foreach ($names as $name) {
            //Count indentation level (whitespace characters at the beginning of the line)
            $identSize = strlen($name)-strlen(ltrim($name));

            //If the line is intended more than the last line, we have a new parent element
            if ($identSize > end($indentations)) {
                $current_parent = $last_element;
                //Add the new indentation level to the stack
                $indentations[] = $identSize;
            }
            while ($identSize < end($indentations)) {
                //If the line is intendet less than the last line, we have to go up in the tree
                $current_parent = $current_parent instanceof AbstractStructuralDBElement ? $current_parent->getParent() : null;
                array_pop($indentations);
            }

            $name = trim($name);
            if ('' === $name) {
                //Skip empty lines (StrucuralDBElements must have a name)
                continue;
            }

            /** @var AbstractStructuralDBElement $entity */
            //Create new element with given name. Using the function from the repository, to correctly reuse existing elements

            if ($current_parent instanceof AbstractStructuralDBElement) {
                $new_path = $current_parent->getFullPath("->") . '->' . $name;
            } else {
                $new_path = $name;
            }
            //We can only use the getNewEntityFromPath function, if the repository is a StructuralDBElementRepository
            if ($repo instanceof StructuralDBElementRepository) {
                $entities = $repo->getNewEntityFromPath($new_path);
                $entity = end($entities);
                if ($entity === false) {
                    throw new InvalidArgumentException('getNewEntityFromPath returned an empty array!');
                }
            } else { //Otherwise just create a new entity
                $entity = new $class_name;
                $entity->setName($name);
            }


            //Validate entity
            $tmp = $this->validator->validate($entity);
            //If no error occured, write entry to DB:
            if (0 === count($tmp)) {
                $valid_entities[] = $entity;
            } else { //Otherwise log error
                $errors[] = [
                    'entity' => $entity,
                    'violations' => $tmp,
                ];
            }

            $last_element = $entity;
        }

        return $valid_entities;
    }

    /**
     * Import data from a string.
     * @param  string  $data The serialized data which should be imported
     * @param  array  $options The options for the import process
     * @param  array  $errors An array which will be filled with the validation errors, if any occurs during import
     * @param-out array<string, array{'entity': object, 'violations': ConstraintViolationListInterface}> $errors
     * @return array An array containing all valid imported entities
     */
    public function importString(string $data, array $options = [], array &$errors = []): array
    {
        //Try to detect the text encoding of the data and convert it to UTF-8
        $data = mb_convert_encoding($data, 'UTF-8', mb_detect_encoding($data, self::ENCODINGS));

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        if (!is_a($options['class'], AbstractNamedDBElement::class, true)) {
            throw new InvalidArgumentException('$class_name must be an AbstractNamedDBElement type!');
        }

        $groups = ['import']; //We can only import data, that is marked with the group "import"
        //Add group when the children should be preserved
        if ($options['preserve_children']) {
            $groups[] = 'include_children';
        }

        //The [] behind class_name denotes that we expect an array.
        $entities = $this->serializer->deserialize($data, $options['class'].'[]', $options['format'],
            [
                'groups' => $groups,
                'csv_delimiter' => $options['csv_delimiter'],
                'create_unknown_datastructures' => $options['create_unknown_datastructures'],
                'path_delimiter' => $options['path_delimiter'],
                'partdb_import' => true,
                //Disable API Platform normalizer, as we don't want to use it here
                SkippableItemNormalizer::DISABLE_ITEM_NORMALIZER => true,
            ]);

        //Ensure we have an array of entity elements.
        if (!is_array($entities)) {
            $entities = [$entities];
        }

        //The serializer has only set the children attributes. We also have to change the parent value (the real value in DB)
        if ($entities[0] instanceof AbstractStructuralDBElement) {
            $this->correctParentEntites($entities, null);
        }

        //Set the parent of the imported elements to the given options
        foreach ($entities as $entity) {
            if ($entity instanceof AbstractStructuralDBElement) {
                $entity->setParent($options['parent']);
            }
            if ($entity instanceof Part) {
                if ($options['part_category']) {
                    $entity->setCategory($options['part_category']);
                }
                if ($options['part_needs_review']) {
                    $entity->setNeedsReview(true);
                }
            }
        }

        //Validate the entities
        $errors = [];

        //Iterate over each $entity write it to DB.
        foreach ($entities as $key => $entity) {
            //Ensure that entity is a NamedDBElement
            if (!$entity instanceof AbstractNamedDBElement) {
                throw new \RuntimeException("Encountered an entity that is not a NamedDBElement!");
            }

            //Validate entity
            $tmp = $this->validator->validate($entity);

            if (count($tmp) > 0) { //Log validation errors to global log.
                $name = $entity instanceof AbstractStructuralDBElement ? $entity->getFullPath() : $entity->getName();

                if (trim($name) === '') {
                    $name = 'Row ' . (string) $key;
                }

                $errors[$name] = [
                    'violations' => $tmp,
                    'entity' => $entity,
                ];

                //Remove the invalid entity from the array
                unset($entities[$key]);
            }
        }

        return $entities;
    }

    protected function configureOptions(OptionsResolver $resolver): OptionsResolver
    {
        $resolver->setDefaults([
            'csv_delimiter' => ';', //The separator to use when importing csv files
            'format' => 'json', //The format of the file that should be imported
            'class' => AbstractNamedDBElement::class,
            'preserve_children' => true,
            'parent' => null, //The parent element to which the imported elements should be added
            'abort_on_validation_error' => true,
            'part_category' => null,
            'part_needs_review' => false, //If true, the imported parts will be marked as "needs review", otherwise the value from the file will be used
            'create_unknown_datastructures' => true, //If true, unknown datastructures (categories, footprints, etc.) will be created on the fly
            'path_delimiter' => '->', //The delimiter used to separate the path elements in the name of a structural element
        ]);

        $resolver->setAllowedValues('format', ['csv', 'json', 'xml', 'yaml']);
        $resolver->setAllowedTypes('csv_delimiter', 'string');
        $resolver->setAllowedTypes('preserve_children', 'bool');
        $resolver->setAllowedTypes('class', 'string');
        $resolver->setAllowedTypes('part_category', [Category::class, 'null']);
        $resolver->setAllowedTypes('part_needs_review', 'bool');

        return $resolver;
    }

    /**
     * This method deserializes the given file and writes the entities to the database (and flush the db).
     * The imported elements will be checked (validated) before written to database.
     *
     * @param File   $file       the file that should be used for importing
     * @param array  $options    options for the import process
     * @param-out AbstractNamedDBElement[]  $entities  The imported entities are returned in this array
     *
     * @return array<string, array{'entity': object, 'violations': ConstraintViolationListInterface}> An associative array containing an ConstraintViolationList and the entity name as key are returned,
     *               if an error happened during validation. When everything was successfully, the array should be empty.
     */
    public function importFileAndPersistToDB(File $file, array $options = [], array &$entities = []): array
    {
        $options = $this->configureOptions(new OptionsResolver())->resolve($options);

        $errors = [];
        $entities = $this->importFile($file, $options, $errors);

        //When we should abort on validation error, do nothing and return the errors
        if (!empty($errors) && $options['abort_on_validation_error']) {
            return $errors;
        }

        //Iterate over each $entity write it to DB (the invalid entities were already filtered out).
        foreach ($entities as $entity) {
            $this->em->persist($entity);
        }

        //Save changes to database, when no error happened, or we should continue on error.
        $this->em->flush();

        return $errors;
    }

    /**
     * This method converts (deserialize) a (uploaded) file to an array of entities with the given class.
     * The imported elements are not persisted to database yet, so you have to do it yourself.
     *
     * @param File   $file       the file that should be used for importing
     * @param array  $options    options for the import process
     * @param-out  array<string, array{'entity': object, 'violations': ConstraintViolationListInterface}>  $errors
     *
     * @return AbstractNamedDBElement[] an array containing the deserialized elements
     */
    public function importFile(File $file, array $options = [], array &$errors = []): array
    {
        return $this->importString($file->getContent(), $options, $errors);
    }


    /**
     * Determines the format to import based on the file extension.
     * @param  string  $extension The file extension to use
     * @return string The format to use (json, xml, csv, yaml), or null if the extension is unknown
     */
    public function determineFormat(string $extension): ?string
    {
        //Convert the extension to lower case
        $extension = strtolower($extension);

        return match ($extension) {
            'json' => 'json',
            'xml' => 'xml',
            'csv', 'tsv' => 'csv',
            'yaml', 'yml' => 'yaml',
            default => null,
        };
    }

    /**
     * This functions corrects the parent setting based on the children value of the parent.
     *
     * @param iterable                         $entities the list of entities that should be fixed
     * @param  AbstractStructuralDBElement|null  $parent   the parent, to which the entity should be set
     */
    protected function correctParentEntites(iterable $entities, ?AbstractStructuralDBElement $parent = null): void
    {
        foreach ($entities as $entity) {
            /** @var AbstractStructuralDBElement $entity */
            $entity->setParent($parent);
            //Do the same for the children of entity
            $this->correctParentEntites($entity->getChildren(), $entity);
        }
    }
}
