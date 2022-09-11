<?php

namespace App\Form\Filters;

use App\DataTables\Filters\AttachmentFilter;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\AttachmentTypeAttachment;
use App\Entity\Attachments\CategoryAttachment;
use App\Entity\Attachments\CurrencyAttachment;
use App\Entity\Attachments\DeviceAttachment;
use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Attachments\GroupAttachment;
use App\Entity\Attachments\LabelAttachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Attachments\StorelocationAttachment;
use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Attachments\UserAttachment;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Supplier;
use App\Form\AdminPages\FootprintAdminForm;
use App\Form\Filters\Constraints\BooleanConstraintType;
use App\Form\Filters\Constraints\DateTimeConstraintType;
use App\Form\Filters\Constraints\InstanceOfConstraintType;
use App\Form\Filters\Constraints\NumberConstraintType;
use App\Form\Filters\Constraints\StructuralEntityConstraintType;
use App\Form\Filters\Constraints\TextConstraintType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttachmentFilterType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => AttachmentFilter::class,
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

        $builder->add('name', TextConstraintType::class, [
            'label' => 'attachment.edit.name',
        ]);

        $builder->add('targetType', InstanceOfConstraintType::class, [
            'label' => 'attachment.table.element_type',
            'choices' => [
                'part.label' => PartAttachment::class,
                'attachment_type.label' => AttachmentTypeAttachment::class,
                'category.label' => CategoryAttachment::class,
                'currency.label' => CurrencyAttachment::class,
                'device.label' => DeviceAttachment::class,
                'footprint.label' => FootprintAttachment::class,
                'group.label' => GroupAttachment::class,
                'label_profile.label' => LabelAttachment::class,
                'manufacturer.label' => Manufacturer::class,
                'measurement_unit.label' => MeasurementUnit::class,
                'storelocation.label' => StorelocationAttachment::class,
                'supplier.label' => SupplierAttachment::class,
                'user.label' => UserAttachment::class,
            ]
        ]);

        $builder->add('attachmentType', StructuralEntityConstraintType::class, [
            'label' => 'attachment.attachment_type',
            'entity_class' => AttachmentType::class
        ]);

        $builder->add('showInTable', BooleanConstraintType::class, [
            'label' => 'attachment.edit.show_in_table'
        ]);

        $builder->add('lastModified', DateTimeConstraintType::class, [
            'label' => 'lastModified'
        ]);

        $builder->add('addedDate', DateTimeConstraintType::class, [
            'label' => 'createdAt'
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'filter.submit',
        ]);

        $builder->add('discard', ResetType::class, [
            'label' => 'filter.discard',
        ]);
    }
}