<?php
/**
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/.
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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

namespace App\Security\EntityListeners;

use App\Entity\Base\DBElement;
use App\Security\Annotations\ColumnSecurity;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\PostLoad;
use Doctrine\ORM\Mapping\PreUpdate;
use ReflectionClass;
use Symfony\Component\Security\Core\Security;

/**
 * The purpose of this class is to hook into the doctrine entity lifecycle and restrict access to entity informations
 * configured by ColoumnSecurity Annotation.
 *
 * If a user does not have access to an coloumn, it will be filled, with a placeholder, after doctrine loading is finished.
 * The edit process is also catched, so that these placeholders, does not get saved to database.
 */
class ElementPermissionListener
{
    protected $security;
    protected $reader;

    public function __construct(Security $security, Reader $reader)
    {
        $this->security = $security;
        $this->reader = $reader;
    }

    /**
     * @PostLoad
     *
     * This function is called after doctrine filled, the entity properties with db values.
     * We use this, to check if the user is allowed to access these properties, and if not, we write a placeholder
     * into the element properties, so that a user only gets non sensitve data.
     */
    public function postLoadHandler(DBElement $element, LifecycleEventArgs $event)
    {
        //Read Annotations and properties.
        $reflectionClass = new ReflectionClass($element);
        $properties = $reflectionClass->getProperties();

        foreach ($properties as $property) {
            /**
             * @var ColumnSecurity
             */
            $annotation = $this->reader->getPropertyAnnotation($property,
                ColumnSecurity::class);

            //Check if user is allowed to read info, otherwise apply placeholder
            if ((null !== $annotation) && !$this->security->isGranted($annotation->getReadOperationName(), $element)) {
                $property->setAccessible(true);
                $property->setValue($element, $annotation->getPlaceholder());
            }
        }
    }

    /**
     * @PreUpdate
     * This function is called before Doctrine saves the property values to the database.
     * We use this function to revert the changes made in postLoadHandler(), so nothing gets changed persistently.
     */
    public function preUpdateHandler(DBElement $element, PreUpdateEventArgs $event)
    {
        $reflectionClass = new ReflectionClass($element);
        $properties = $reflectionClass->getProperties();

        foreach ($properties as $property) {
            /**
             * @var ColumnSecurity
             */
            $annotation = $this->reader->getPropertyAnnotation($property,
                ColumnSecurity::class);

            if (null !== $annotation) {
                $field_name = $property->getName();

                //Check if user is allowed to edit info, otherwise overwrite the new value
                // so that nothing is changed in the DB.
                if ($event->hasChangedField($field_name) &&
                    !$this->security->isGranted($annotation->getEditOperationName(), $element)) {
                    $event->setNewValue($field_name, $event->getOldValue($field_name));
                }
            }
        }
    }
}
