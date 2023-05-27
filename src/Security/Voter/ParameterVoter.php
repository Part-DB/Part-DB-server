<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Security\Voter;

use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parameters\AttachmentTypeParameter;
use App\Entity\Parameters\CategoryParameter;
use App\Entity\Parameters\CurrencyParameter;
use App\Entity\Parameters\ProjectParameter;
use App\Entity\Parameters\FootprintParameter;
use App\Entity\Parameters\GroupParameter;
use App\Entity\Parameters\ManufacturerParameter;
use App\Entity\Parameters\MeasurementUnitParameter;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parameters\StorelocationParameter;
use App\Entity\Parameters\SupplierParameter;
use App\Entity\UserSystem\User;
use App\Services\UserSystem\PermissionManager;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Security\Core\Security;

class ParameterVoter extends ExtendedVoter
{

    protected \Symfony\Bundle\SecurityBundle\Security $security;

    public function __construct(PermissionManager $resolver, EntityManagerInterface $entityManager, \Symfony\Bundle\SecurityBundle\Security $security)
    {
        $this->security = $security;
        parent::__construct($resolver, $entityManager);
    }

    protected function voteOnUser(string $attribute, $subject, User $user): bool
    {
        //return $this->resolver->inherit($user, 'attachments', $attribute) ?? false;

        if (!is_a($subject, AbstractParameter::class, true)) {
            return false;
        }

        if (is_object($subject)) {
            //If the attachment has no element (which should not happen), we deny access, as we can not determine if the user is allowed to access the associated element
            $target_element = $subject->getElement();
            if ($target_element !== null) {
                //Depending on the operation delegate either to the attachments element or to the attachment permission


                switch ($attribute) {
                    //We can view the attachment if we can view the element
                    case 'read':
                    case 'view':
                        $operation = 'read';
                        break;
                    //We can edit/create/delete the attachment if we can edit the element
                    case 'edit':
                    case 'create':
                    case 'delete':
                        $operation = 'edit';
                        break;
                    case 'show_history':
                        $operation = 'show_history';
                        break;
                    case 'revert_element':
                        $operation = 'revert_element';
                        break;
                    default:
                        throw new RuntimeException('Unknown operation: '.$attribute);
                }

                return $this->security->isGranted($operation, $target_element);
            }
        }

        //If we do not have a concrete element (or we just got a string as value), we delegate to the different categories
        if (is_a($subject, AttachmentTypeParameter::class, true)) {
            $param = 'attachment_types';
        } elseif (is_a($subject, CategoryParameter::class, true)) {
            $param = 'categories';
        } elseif (is_a($subject, CurrencyParameter::class, true)) {
            $param = 'currencies';
        } elseif (is_a($subject, ProjectParameter::class, true)) {
            $param = 'projects';
        } elseif (is_a($subject, FootprintParameter::class, true)) {
            $param = 'footprints';
        } elseif (is_a($subject, GroupParameter::class, true)) {
            $param = 'groups';
        } elseif (is_a($subject, ManufacturerParameter::class, true)) {
            $param = 'manufacturers';
        } elseif (is_a($subject, MeasurementUnitParameter::class, true)) {
            $param = 'measurement_units';
        } elseif (is_a($subject, PartParameter::class, true)) {
            $param = 'parts';
        } elseif (is_a($subject, StorelocationParameter::class, true)) {
            $param = 'storelocations';
        } elseif (is_a($subject, SupplierParameter::class, true)) {
            $param = 'suppliers';
        } elseif ($subject === AbstractParameter::class) {
            //If the subject was deleted, we can not determine the type properly, so we just use the parts permission
            $param = 'parts';
        }
        else {
            throw new RuntimeException('Encountered unknown Parameter type: ' . (is_object($subject) ? get_class($subject) : $subject));
        }

        return $this->resolver->inherit($user, $param, $attribute) ?? false;
    }

    protected function supports(string $attribute, $subject): bool
    {
        if (is_a($subject, AbstractParameter::class, true)) {
            //These are the allowed attributes
            return in_array($attribute, ['read', 'edit', 'delete', 'create', 'show_history', 'revert_element'], true);
        }

        //Allow class name as subject
        return false;
    }
}