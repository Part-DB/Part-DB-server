<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Services;

use App\Entity\Base\AbstractStructuralDBElement;
use function count;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use function is_array;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class EntityImporter
{
    protected $serializer;
    protected $em;
    protected $validator;

    public function __construct(SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator)
    {
        $this->serializer = $serializer;
        $this->em = $em;
        $this->validator = $validator;
    }

    /**
     * Creates many entries at once, based on a (text) list of name.
     * The created enties are not persisted to database yet, so you have to do it yourself.
     *
     * @param string                           $lines      The list of names seperated by \n
     * @param string                           $class_name The name of the class for which the entities should be created
     * @param AbstractStructuralDBElement|null $parent     the element which will be used as parent element for new elements
     * @param array                            $errors     an associative array containing all validation errors
     *
     * @return AbstractStructuralDBElement[] An array containing all valid imported entities (with the type $class_name)
     */
    public function massCreation(string $lines, string $class_name, ?AbstractStructuralDBElement $parent = null, array &$errors = []): array
    {
        //Expand every line to a single entry:
        $names = explode("\n", $lines);

        if (!is_a($class_name, AbstractStructuralDBElement::class, true)) {
            throw new InvalidArgumentException('$class_name must be a StructuralDBElement type!');
        }
        if (null !== $parent && !is_a($parent, $class_name)) {
            throw new InvalidArgumentException('$parent must have the same type as specified in $class_name!');
        }

        $errors = [];
        $valid_entities = [];

        foreach ($names as $name) {
            $name = trim($name);
            if ('' === $name) {
                //Skip empty lines (StrucuralDBElements must have a name)
                continue;
            }
            /** @var AbstractStructuralDBElement $entity */
            //Create new element with given name
            $entity = new $class_name();
            $entity->setName($name);
            $entity->setParent($parent);

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
        }

        return $valid_entities;
    }

    /**
     * This methods deserializes the given file and saves it database.
     * The imported elements will be checked (validated) before written to database.
     *
     * @param File   $file       the file that should be used for importing
     * @param string $class_name the class name of the enitity that should be imported
     * @param array  $options    options for the import process
     *
     * @return array An associative array containing an ConstraintViolationList and the entity name as key are returned,
     *               if an error happened during validation. When everything was successfull, the array should be empty.
     */
    public function fileToDBEntities(File $file, string $class_name, array $options = []): array
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $options = $resolver->resolve($options);

        $entities = $this->fileToEntityArray($file, $class_name, $options);

        $errors = [];

        //Iterate over each $entity write it to DB.
        foreach ($entities as $entity) {
            /** @var AbstractStructuralDBElement $entity */
            //Move every imported entity to the selected parent
            $entity->setParent($options['parent']);

            //Validate entity
            $tmp = $this->validator->validate($entity);

            //When no validation error occured, persist entity to database (cascade must be set in entity)
            if (null === $tmp) {
                $this->em->persist($entity);
            } else { //Log validation errors to global log.
                $errors[$entity->getFullPath()] = $tmp;
            }
        }

        //Save changes to database, when no error happened, or we should continue on error.
        if (empty($errors) || false === $options['abort_on_validation_error']) {
            $this->em->flush();
        }

        return $errors;
    }

    /**
     * This method converts (deserialize) a (uploaded) file to an array of entities with the given class.
     *
     * The imported elements will NOT be validated. If you want to use the result array, you have to validate it by yourself.
     *
     * @param File   $file       the file that should be used for importing
     * @param string $class_name the class name of the enitity that should be imported
     * @param array  $options    options for the import process
     *
     * @return array an array containing the deserialized elements
     */
    public function fileToEntityArray(File $file, string $class_name, array $options = []): array
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $options = $resolver->resolve($options);

        //Read file contents
        $content = file_get_contents($file->getRealPath());

        $groups = ['simple'];
        //Add group when the children should be preserved
        if ($options['preserve_children']) {
            $groups[] = 'include_children';
        }

        //The [] behind class_name denotes that we expect an array.
        $entities = $this->serializer->deserialize($content, $class_name.'[]', $options['format'],
            [
                'groups' => $groups,
                'csv_delimiter' => $options['csv_separator'],
            ]);

        //Ensure we have an array of entitity elements.
        if (!is_array($entities)) {
            $entities = [$entities];
        }

        //The serializer has only set the children attributes. We also have to change the parent value (the real value in DB)
        if ($entities[0] instanceof AbstractStructuralDBElement) {
            $this->correctParentEntites($entities, null);
        }

        return $entities;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csv_separator' => ';',
            'format' => 'json',
            'preserve_children' => true,
            'parent' => null,
            'abort_on_validation_error' => true,
        ]);
    }

    /**
     * This functions corrects the parent setting based on the children value of the parent.
     *
     * @param iterable                         $entities the list of entities that should be fixed
     * @param AbstractStructuralDBElement|null $parent   the parent, to which the entity should be set
     */
    protected function correctParentEntites(iterable $entities, $parent = null): void
    {
        foreach ($entities as $entity) {
            /** @var AbstractStructuralDBElement $entity */
            $entity->setParent($parent);
            //Do the same for the children of entity
            $this->correctParentEntites($entity->getChildren(), $entity);
        }
    }
}
