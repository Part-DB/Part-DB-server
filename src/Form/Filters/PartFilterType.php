<?php

namespace App\Form\Filters;

use App\DataTables\Filters\PartFilter;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Storelocation;
use App\Form\Filters\Constraints\BooleanConstraintType;
use App\Form\Filters\Constraints\ChoiceConstraintType;
use App\Form\Filters\Constraints\DateTimeConstraintType;
use App\Form\Filters\Constraints\NumberConstraintType;
use App\Form\Filters\Constraints\StructuralEntityConstraintType;
use App\Form\Filters\Constraints\TagsConstraintType;
use App\Form\Filters\Constraints\TextConstraintType;
use Svg\Tag\Text;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PartFilterType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => PartFilter::class,
            'csrf_protection' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /*
         * Common tab
         */

        $builder->add('name', TextConstraintType::class, [
            'label' => 'part.edit.name',
        ]);

        $builder->add('description', TextConstraintType::class, [
            'label' => 'part.edit.description',
        ]);

        $builder->add('category', StructuralEntityConstraintType::class, [
            'label' => 'part.edit.category',
            'entity_class' => Category::class
        ]);

        $builder->add('footprint', StructuralEntityConstraintType::class, [
            'label' => 'part.edit.footprint',
            'entity_class' => Footprint::class
        ]);

        $builder->add('tags', TagsConstraintType::class, [
            'label' => 'part.edit.tags'
        ]);

        $builder->add('comment', TextConstraintType::class, [
            'label' => 'part.edit.comment'
        ]);

        /*
         * Advanced tab
         */

        $builder->add('dbId', NumberConstraintType::class, [
            'label' => 'part.filter.dbId',
            'min' => 1,
            'step' => 1,
        ]);

        $builder->add('favorite', BooleanConstraintType::class, [
            'label' => 'part.edit.is_favorite'
        ]);

        $builder->add('needsReview', BooleanConstraintType::class, [
            'label' => 'part.edit.needs_review'
        ]);

        $builder->add('mass', NumberConstraintType::class, [
            'label' => 'part.edit.mass',
            'text_suffix' => 'g',
            'min' => 0,
        ]);

        $builder->add('measurementUnit', StructuralEntityConstraintType::class, [
            'label' => 'part.edit.partUnit',
            'entity_class' => MeasurementUnit::class
        ]);

        $builder->add('lastModified', DateTimeConstraintType::class, [
            'label' => 'lastModified'
        ]);

        $builder->add('addedDate', DateTimeConstraintType::class, [
            'label' => 'createdAt'
        ]);


        /*
         * Manufacturer tab
         */

        $builder->add('manufacturer', StructuralEntityConstraintType::class, [
            'label' => 'part.edit.manufacturer.label',
            'entity_class' => Manufacturer::class
        ]);

        $builder->add('manufacturer_product_url', TextConstraintType::class, [
            'label' => 'part.edit.manufacturer_url.label'
        ]);

        $builder->add('manufacturer_product_number', TextConstraintType::class, [
            'label' => 'part.edit.mpn'
        ]);

        $status_choices = [
            'm_status.unknown' => '',
            'm_status.announced' => 'announced',
            'm_status.active' => 'active',
            'm_status.nrfnd' => 'nrfnd',
            'm_status.eol' => 'eol',
            'm_status.discontinued' => 'discontinued',
        ];

        $builder->add('manufacturing_status', ChoiceConstraintType::class, [
            'label' => 'part.edit.manufacturing_status',
            'choices' => $status_choices,
        ]);

        /*
         * Purchasee informations
         */

        $builder->add('supplier', StructuralEntityConstraintType::class, [
            'label' => 'supplier.label',
            'entity_class' => Manufacturer::class
        ]);

        $builder->add('orderdetailsCount', NumberConstraintType::class, [
            'label' => 'part.filter.orderdetails_count',
           'step' => 1,
           'min' => 0,
        ]);


        /*
         * Stocks tabs
         */
        $builder->add('storelocation', StructuralEntityConstraintType::class, [
            'label' => 'storelocation.label',
            'entity_class' => Storelocation::class
        ]);

        $builder->add('minAmount', NumberConstraintType::class, [
            'label' => 'part.edit.mininstock',
            'min' => 0,
        ]);

        $builder->add('lotCount', NumberConstraintType::class, [
            'label' => 'part.filter.lot_count',
            'min' => 0,
            'step' => 1,
        ]);

        $builder->add('lotNeedsRefill', BooleanConstraintType::class, [
            'label' => 'part.filter.lotNeedsRefill'
        ]);

        $builder->add('lotUnknownAmount', BooleanConstraintType::class, [
            'label' => 'part.filter.lotUnknwonAmount'
        ]);

        $builder->add('lotExpirationDate', DateTimeConstraintType::class, [
            'label' => 'part.filter.lotExpirationDate',
            'input_type' => DateType::class,
        ]);

        /**
         * Attachments count
         */
        $builder->add('attachmentsCount', NumberConstraintType::class, [
            'label' => 'part.filter.attachments_count',
            'step' => 1,
            'min' => 0,
        ]);

        $builder->add('attachmentType', StructuralEntityConstraintType::class, [
            'label' => 'attachment.attachment_type',
            'entity_class' => AttachmentType::class
        ]);

        $builder->add('attachmentName', TextConstraintType::class, [
            'label' => 'part.filter.attachmentName',
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'Update',
        ]);

        $builder->add('reset', ResetType::class, [
            'label' => 'Reset',
        ]);

    }
}