<?php

declare(strict_types=1);

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
use App\Entity\LogSystem\LogLevel;
use App\Entity\LogSystem\LogTargetType;
use App\Entity\LogSystem\PartStockChangedLogEntry;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\CollectionElementDeleted;
use App\Entity\LogSystem\DatabaseUpdatedLogEntry;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementDeletedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\LogSystem\LegacyInstockChangedLogEntry;
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
use App\Form\Filters\Constraints\EnumConstraintType;
use App\Form\Filters\Constraints\InstanceOfConstraintType;
use App\Form\Filters\Constraints\NumberConstraintType;
use App\Form\Filters\Constraints\UserEntityConstraintType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LogFilterType extends AbstractType
{
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
        'log.type.part_stock_changed' => PartStockChangedLogEntry::class,

        //Legacy entries
        'log.type.instock_changed' => LegacyInstockChangedLogEntry::class,
    ];

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => LogFilter::class,
            'csrf_protection' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('dbId', NumberConstraintType::class, [
            'label' => 'part.filter.dbId',
            'min' => 1,
            'step' => 1,
        ]);

        $builder->add('timestamp', DateTimeConstraintType::class, [
            'label' => 'log.timestamp',
        ]);



        $builder->add('level', EnumConstraintType::class, [
            'label' => 'log.level',
            'enum_class' => LogLevel::class,
            'choice_label' => fn(LogLevel $level): string => 'log.level.' . $level->toPSR3LevelString(),
        ]);

        $builder->add('eventType', InstanceOfConstraintType::class, [
            'label' => 'log.type',
            'choices' => self::TARGET_TYPE_CHOICES
        ]);

        $builder->add('user', UserEntityConstraintType::class, [
           'label'  => 'log.user',
        ]);

        $builder->add('targetType', EnumConstraintType::class, [
            'label' => 'log.target_type',
            'enum_class' => LogTargetType::class,
            'choice_label' => fn(LogTargetType $type): string => match ($type) {
                LogTargetType::NONE => 'log.target_type.none',
                LogTargetType::USER => 'user.label',
                LogTargetType::ATTACHMENT => 'attachment.label',
                LogTargetType::ATTACHMENT_TYPE => 'attachment_type.label',
                LogTargetType::CATEGORY => 'category.label',
                LogTargetType::PROJECT => 'project.label',
                LogTargetType::BOM_ENTRY => 'project_bom_entry.label',
                LogTargetType::FOOTPRINT => 'footprint.label',
                LogTargetType::GROUP => 'group.label',
                LogTargetType::MANUFACTURER => 'manufacturer.label',
                LogTargetType::PART => 'part.label',
                LogTargetType::STORELOCATION => 'storelocation.label',
                LogTargetType::SUPPLIER => 'supplier.label',
                LogTargetType::PART_LOT => 'part_lot.label',
                LogTargetType::CURRENCY => 'currency.label',
                LogTargetType::ORDERDETAIL => 'orderdetail.label',
                LogTargetType::PRICEDETAIL => 'pricedetail.label',
                LogTargetType::MEASUREMENT_UNIT => 'measurement_unit.label',
                LogTargetType::PARAMETER => 'parameter.label',
                LogTargetType::LABEL_PROFILE => 'label_profile.label',
            },
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
