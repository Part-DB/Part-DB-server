<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace App\Services\ImportExportSystem\PartKeeprImporter;

use Doctrine\ORM\Id\AssignedGenerator;
use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Contracts\TimeStampableInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * This trait contains helper functions for the PartKeeprImporter.
 */
trait PKImportHelperTrait
{
    protected EntityManagerInterface $em;
    protected PropertyAccessorInterface $propertyAccessor;

    private ?AttachmentType $import_attachment_type = null;

    /**
     * Converts a PartKeepr attachment/image row to an Attachment entity.
     * @param  array  $attachment_row The attachment row from the PartKeepr database
     * @param  string  $target_class The target class for the attachment
     * @param  string  $type The type of the attachment (attachment or image)
     * @throws \Exception
     */
    protected function convertAttachmentDataToEntity(array $attachment_row, string $target_class, string $type): Attachment
    {
        //By default, we use the cached version
        if (!$this->import_attachment_type) {
            //Get the import attachment type
            $this->import_attachment_type =  $this->em->getRepository(AttachmentType::class)->findOneBy([
                'name' => 'PartKeepr Attachment'
            ]);
            if (!$this->import_attachment_type) { //If not existing in DB create it
                $this->import_attachment_type = new AttachmentType();
                $this->import_attachment_type->setName('PartKeepr Attachment');
                $this->em->persist($this->import_attachment_type);
            }
        }

        if (!in_array($type, ['attachment', 'image'], true)) {
            throw new \InvalidArgumentException(sprintf('The type %s is not a valid attachment type', $type));
        }

        if (!is_a($target_class, Attachment::class, true)) {
            throw new \InvalidArgumentException(sprintf('The target class %s is not a subclass of %s', $target_class, Attachment::class));
        }

        /** @var Attachment $attachment */
        $attachment = new $target_class();
        if (!empty($attachment_row['description'])) {
            $attachment->setName($attachment_row['description']);
        } else {
            $attachment->setName($attachment_row['originalname']);
        }
        $attachment->setFilename($attachment_row['originalname']);
        $attachment->setAttachmentType($this->import_attachment_type);
        $this->setCreationDate($attachment, $attachment_row['created']);

        //Determine file extension (if the extension is empty, we use the original extension)
        if (empty($attachment_row['extension'])) {
            //Use mime type to determine the extension like PartKeepr does in legacy implementation (just use the second part of the mime type)
            //See UploadedFile.php:291 in PartKeepr (https://github.com/partkeepr/PartKeepr/blob/f6176c3354b24fa39ac8bc4328ee0df91de3d5b6/src/PartKeepr/UploadedFileBundle/Entity/UploadedFile.php#L291)
            if (!empty ($attachment_row['mimetype'])) {
                $attachment_row['extension'] = explode('/', (string) $attachment_row['mimetype'])[1];
            } else {
                //If the mime type is empty, we use the original extension
                $attachment_row['extension'] = pathinfo((string) $attachment_row['originalname'], PATHINFO_EXTENSION);
            }

        }

        //Determine file path
        //Images are stored in the (public) media folder, attachments in the (private) uploads/ folder
        $path = $type === 'attachment' ? '%SECURE%' : '%MEDIA%';
        //The folder is the type of the attachment from the PartKeepr database
        $path .= '/'.$attachment_row['type'];
        //Next comes the filename plus extension
        $path .= '/'.$attachment_row['filename'].'.'.$attachment_row['extension'];

        $attachment->setPath($path);

        return $attachment;
    }

    /**
     * Imports the attachments from the given data
     * @param  array  $data The PartKeepr database
     * @param  string  $table_name The table name for the attachments (if it contains "image", it will be treated as an image)
     * @param  string  $target_class The target class (e.g. Part)
     * @param  string  $target_id_field The field name where the target ID is stored
     * @param  string  $attachment_class The attachment class (e.g. PartAttachment)
     */
    protected function importAttachments(array $data, string $table_name, string $target_class, string $target_id_field, string $attachment_class): void
    {
        //Determine if we have an image or an attachment
        $type = str_contains($table_name, 'image') || str_contains($table_name, 'iclogo') ? 'image' : 'attachment';

        if (!isset($data[$table_name])) {
            throw new \RuntimeException(sprintf('The table %s does not exist in the PartKeepr database', $table_name));
        }

        if (!is_a($target_class, AttachmentContainingDBElement::class, true)) {
            throw new \InvalidArgumentException(sprintf('The target class %s is not a subclass of %s', $target_class, AttachmentContainingDBElement::class));
        }

        if (!is_a($attachment_class, Attachment::class, true)) {
            throw new \InvalidArgumentException(sprintf('The attachment class %s is not a subclass of %s', $attachment_class, Attachment::class));
        }

        //Get the table data
        $table_data = $data[$table_name];
        foreach($table_data as $attachment_row) {
            $attachment = $this->convertAttachmentDataToEntity($attachment_row, $attachment_class, $type);

            //Retrieve the target entity
            $target_id = (int) $attachment_row[$target_id_field];
            /** @var AttachmentContainingDBElement $target */
            $target = $this->em->find($target_class, $target_id);
            if (!$target) {
                throw new \RuntimeException(sprintf('Could not find target entity with ID %s', $target_id));
            }

            $target->addAttachment($attachment);
            $this->em->persist($attachment);
        }

        $this->em->flush();
    }

    /**
     * Assigns the parent to the given entity, using the numerical IDs from the imported data.
     * @return AbstractStructuralDBElement The structural element that was modified (with $element_id)
     */
    protected function setParent(string $class, int|string $element_id, int|string $parent_id): AbstractStructuralDBElement
    {
        $element = $this->em->find($class, (int) $element_id);
        if (!$element) {
            throw new \RuntimeException(sprintf('Could not find element with ID %s', $element_id));
        }

        //If the parent is null, we're done
        if (!$parent_id) {
            return $element;
        }

        $parent = $this->em->find($class, (int) $parent_id);
        if (!$parent) {
            throw new \RuntimeException(sprintf('Could not find parent with ID %s', $parent_id));
        }

        $element->setParent($parent);
        return $element;
    }

    /**
     * Sets the given field of the given entity to the entity with the given ID.
     */
    protected function setAssociationField(AbstractDBElement $element, string $field, string $other_class, $other_id): AbstractDBElement
    {
        //If the parent is null, set the field to null and we're done
        if (!$other_id) {
            $this->propertyAccessor->setValue($element, $field, null);
            return $element;
        }

        $parent = $this->em->find($other_class, (int) $other_id);
        if (!$parent) {
            throw new \RuntimeException(sprintf('Could not find other_class with ID %s', $other_id));
        }

        $this->propertyAccessor->setValue($element, $field, $parent);
        return $element;
    }

    /**
     * Set the ID of an entity to a specific value. Must be called before persisting the entity, but before flushing.
     */
    protected function setIDOfEntity(AbstractDBElement $element, int|string $id): void
    {
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException('ID must be an integer or string');
        }

        $id = (int) $id;

        $metadata = $this->em->getClassMetadata($element::class);
        $metadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new AssignedGenerator());
        $metadata->setIdentifierValues($element, ['id' => $id]);
    }

    /**
     * Sets the creation date of an entity to a specific value.
     * @return void
     * @throws \Exception
     */
    protected function setCreationDate(TimeStampableInterface $entity, ?string $datetime_str): void
    {
        if ($datetime_str !== null && $datetime_str !== '' && $datetime_str !== '0000-00-00 00:00:00') {
            $date = new \DateTime($datetime_str);
        } else {
            $date = null; //Null means "now" at persist time
        }

        $reflectionClass = new \ReflectionClass($entity);
        $property = $reflectionClass->getProperty('addedDate');
        $property->setAccessible(true);
        $property->setValue($entity, $date);
    }

    /**
     * Gets the SI prefix factor for the given prefix ID.
     * Used to convert a value from the PartKeepr database to the PartDB database.
     * @param  array $data
     * @param  int  $prefix_id
     * @return float
     */
    protected function getSIPrefixFactor(array $data, int $prefix_id): float
    {
        if ($prefix_id === 0) {
            return 1.0;
        }

        $prefixes = $data['siprefix'];
        foreach ($prefixes as $prefix) {
            if ((int) $prefix['id'] === $prefix_id) {
                return (int)$prefix['base'] ** (int)$prefix['exponent'];
            }
        }

        throw new \RuntimeException(sprintf('Could not find SI prefix with ID %s', $prefix_id));
    }
}
