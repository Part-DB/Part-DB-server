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

namespace App\Form\Filters;

use App\DataTables\Filters\LogFilter;
use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Devices\Device;
use App\Entity\Devices\DevicePart;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\CollectionElementDeleted;
use App\Entity\LogSystem\DatabaseUpdatedLogEntry;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementDeletedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\LogSystem\InstockChangedLogEntry;
use App\Entity\LogSystem\SecurityEventLogEntry;
use App\Entity\LogSystem\UserLoginLogEntry;
use App\Entity\LogSystem\UserLogoutLogEntry;
use App\Entity\LogSystem\UserNotAllowedLogEntry;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Form\Filters\Constraints\ChoiceConstraintType;
use App\Form\Filters\Constraints\DateTimeConstraintType;
use App\Form\Filters\Constraints\InstanceOfConstraintType;
use App\Form\Filters\Constraints\NumberConstraintType;
use App\Form\Filters\Constraints\StructuralEntityConstraintType;
use App\Form\Filters\Constraints\UserEntityConstraintType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LogFilterType extends AbstractType
{
    protected const LEVEL_CHOICES = [
        'log.level.debug' => AbstractLogEntry::LEVEL_DEBUG,
        'log.level.info' => AbstractLogEntry::LEVEL_INFO,
        'log.level.notice' => AbstractLogEntry::LEVEL_NOTICE,
        'log.level.warning' => AbstractLogEntry::LEVEL_WARNING,
        'log.level.error' => AbstractLogEntry::LEVEL_ERROR,
        'log.level.critical' => AbstractLogEntry::LEVEL_CRITICAL,
        'log.level.alert' => AbstractLogEntry::LEVEL_ALERT,
        'log.level.emergency' => AbstractLogEntry::LEVEL_EMERGENCY,
    ];

    protected const TARGET_TYPE_CHOICES = [
        'log.type.collection_element_deleted' => CollectionElementDeleted::class,
        'log.type.database_updated' => DatabaseUpdatedLogEntry::class,
        'log.type.element_created' => ElementCreatedLogEntry::class,
        'log.type.element_deleted' => ElementDeletedLogEntry::class,
        'log.type.element_edited' => ElementEditedLogEntry::class,
        'log.type.security' => SecurityEventLogEntry::class,
        'log.type.user_login' => UserLoginLogEntry::class,
        'log.type.user_logout' => UserLogoutLogEntry::class,
        'log.type.user_not_allowed' => UserNotAllowedLogEntry::class,

        //Legacy entries
        'log.type.instock_changed' => InstockChangedLogEntry::class,
    ];

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => LogFilter::class,
            'csrf_protection' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('dbId', NumberConstraintType::class, [
            'label' => 'part.filter.dbId',
            'min' => 1,
            'step' => 1,
        ]);

        $builder->add('timestamp', DateTimeConstraintType::class, [
            'label' => 'log.timestamp',
        ]);



        $builder->add('level', ChoiceConstraintType::class, [
            'label' => 'log.level',
            'choices' => self::LEVEL_CHOICES,
        ]);

        $builder->add('eventType', InstanceOfConstraintType::class, [
            'label' => 'log.type',
            'choices' => self::TARGET_TYPE_CHOICES
        ]);

        $builder->add('user', UserEntityConstraintType::class, [
           'label'  => 'log.user',
        ]);

        $builder->add('targetType', ChoiceConstraintType::class, [
            'label' => 'log.target_type',
            'choices' => [
                'user.label' => AbstractLogEntry::targetTypeClassToID(User::class),
                'attachment.label' => AbstractLogEntry::targetTypeClassToID(Attachment::class),
                'attachment_type.label' => AbstractLogEntry::targetTypeClassToID(AttachmentType::class),
                'category.label' => AbstractLogEntry::targetTypeClassToID(Category::class),
                'device.label' => AbstractLogEntry::targetTypeClassToID(Device::class),
                'device_part.label' => AbstractLogEntry::targetTypeClassToID(DevicePart::class),
                'footprint.label' => AbstractLogEntry::targetTypeClassToID(Footprint::class),
                'group.label' => AbstractLogEntry::targetTypeClassToID(Group::class),
                'manufacturer.label' => AbstractLogEntry::targetTypeClassToID(Manufacturer::class),
                'part.label' => AbstractLogEntry::targetTypeClassToID(Part::class),
                'storelocation.label' => AbstractLogEntry::targetTypeClassToID(Storelocation::class),
                'supplier.label' => AbstractLogEntry::targetTypeClassToID(Supplier::class),
                'part_lot.label' => AbstractLogEntry::targetTypeClassToID(PartLot::class),
                'currency.label' => AbstractLogEntry::targetTypeClassToID(Currency::class),
                'orderdetail.label' => AbstractLogEntry::targetTypeClassToID(Orderdetail::class),
                'pricedetail.label' => AbstractLogEntry::targetTypeClassToID(Pricedetail::class),
                'measurement_unit.label' => AbstractLogEntry::targetTypeClassToID(MeasurementUnit::class),
                'parameter.label' => AbstractLogEntry::targetTypeClassToID(AbstractParameter::class),
                'label_profile.label' => AbstractLogEntry::targetTypeClassToID(LabelProfile::class),
            ]
        ]);

        $builder->add('targetId', NumberConstraintType::class, [
           'label' => 'log.target_id',
            'min' => 1,
            'step' => 1,
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'filter.submit',
        ]);

        $builder->add('discard', ResetType::class, [
            'label' => 'filter.discard',
        ]);
    }
}