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

namespace App\Security\EntityListeners;

use App\Entity\Base\AbstractDBElement;
use App\Entity\UserSystem\User;
use App\Security\Annotations\ColumnSecurity;
use function count;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\PostLoad;
use function get_class;
use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\Security\Core\Security;

/**
 * The purpose of this class is to hook into the doctrine entity lifecycle and restrict access to entity informations
 * configured by ColoumnSecurity Annotation.
 * If the current programm is running from CLI (like a CLI command), the security checks are disabled.
 * (Commands should be able to do everything they like).
 *
 * If a user does not have access to an coloumn, it will be filled, with a placeholder, after doctrine loading is finished.
 * The edit process is also catched, so that these placeholders, does not get saved to database.
 */
class ElementPermissionListener
{
    protected $security;
    protected $reader;
    protected $em;
    protected $disabled;

    protected $perm_cache;

    public function __construct(Security $security, Reader $reader, EntityManagerInterface $em)
    {
        $this->security = $security;
        $this->reader = $reader;
        $this->em = $em;
        //Disable security when the current program is running from CLI
        $this->disabled = $this->isRunningFromCLI();
        $this->perm_cache = [];
    }

    /**
     * @PostLoad
     * @ORM\PostUpdate()
     * This function is called after doctrine filled, the entity properties with db values.
     * We use this, to check if the user is allowed to access these properties, and if not, we write a placeholder
     * into the element properties, so that a user only gets non sensitve data.
     *
     * This function is also called after an entity was updated, so we dont show the original data to user,
     * after an update.
     */
    public function postLoadHandler(AbstractDBElement $element, LifecycleEventArgs $event): void
    {
        //Do nothing if security is disabled
        if ($this->disabled) {
            return;
        }

        //Read Annotations and properties.
        $reflectionClass = new ReflectionClass($element);
        $properties = $reflectionClass->getProperties();

        foreach ($properties as $property) {
            /** @var ColumnSecurity */
            $annotation = $this->reader->getPropertyAnnotation(
                $property,
                ColumnSecurity::class
            );

            //Check if user is allowed to read info, otherwise apply placeholder
            if ((null !== $annotation) && !$this->isGranted('read', $annotation, $element)) {
                $property->setAccessible(true);
                $value = $annotation->getPlaceholder();

                //Detach placeholder entities, so we dont get cascade errors
                if ($value instanceof AbstractDBElement) {
                    $this->em->detach($value);
                }

                $property->setValue($element, $value);
            }
        }
    }

    /**
     * @ORM\PreFlush()
     * This function is called before flushing. We use it, to remove all placeholders.
     * We do it here and not in preupdate, because this is called before calculating the changeset,
     * and so we dont get problems with orphan removal.
     */
    public function preFlushHandler(AbstractDBElement $element, PreFlushEventArgs $eventArgs): void
    {
        //Do nothing if security is disabled
        if ($this->disabled) {
            return;
        }

        $unitOfWork = $eventArgs->getEntityManager()->getUnitOfWork();

        $reflectionClass = new ReflectionClass($element);
        $properties = $reflectionClass->getProperties();

        $old_data = $unitOfWork->getOriginalEntityData($element);

        foreach ($properties as $property) {
            $annotation = $this->reader->getPropertyAnnotation(
                $property,
                ColumnSecurity::class
            );

            $changed = false;

            //Only set the field if it has an annotation
            if (null !== $annotation) {
                $property->setAccessible(true);

                //If the user is not allowed to edit or read this property, reset all values.
                //Set value to old value, so that there a no change to this property
                if ((!$this->isGranted('read', $annotation, $element)
                        || !$this->isGranted('edit', $annotation, $element)) && isset(
                        $old_data[$property->getName()]
                    )) {
                    $property->setValue($element, $old_data[$property->getName()]);
                    $changed = true;
                }

                if ($changed) {
                    //Schedule for update, so the post update method will be called
                    $unitOfWork->scheduleForUpdate($element);
                }
            }
        }
    }

    /**
     * This function checks if the current script is run from web or from a terminal.
     *
     * @return bool Returns true if the current programm is running from CLI (terminal)
     */
    protected function isRunningFromCLI(): bool
    {
        return empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0;
    }

    /**
     * Checks if access to the property of the given element is granted.
     * This function adds an additional cache layer, where the voters are called only once (to improve performance).
     *
     * @param string            $mode       What operation should be checked. Must be 'read' or 'edit'
     * @param ColumnSecurity    $annotation The annotation of the property that should be checked
     * @param AbstractDBElement $element    The element that should for which should be checked
     *
     * @return bool True if the user is allowed to read that property
     */
    protected function isGranted(string $mode, ColumnSecurity $annotation, AbstractDBElement $element): bool
    {
        if ('read' === $mode) {
            $operation = $annotation->getReadOperationName();
        } elseif ('edit' === $mode) {
            $operation = $annotation->getEditOperationName();
        } else {
            throw new InvalidArgumentException('$mode must be either "read" or "edit"!');
        }

        //Users must always be checked, because its return value can differ if it is the user itself or something else
        if ($element instanceof User) {
            return $this->security->isGranted($operation, $element);
        }

        //Check if we have already have saved the permission, otherwise save it to cache
        if (!isset($this->perm_cache[$mode][get_class($element)][$operation])) {
            $this->perm_cache[$mode][get_class($element)][$operation] = $this->security->isGranted($operation, $element);
        }

        return $this->perm_cache[$mode][get_class($element)][$operation];
    }
}
