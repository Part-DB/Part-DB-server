<?php

namespace App\Form\Filters;

use App\DataTables\Filters\PartFilter;
use App\Form\Filters\Constraints\BooleanConstraintType;
use App\Form\Filters\Constraints\NumberConstraintType;
use Symfony\Component\Form\AbstractType;
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
        $builder->add('favorite', BooleanConstraintType::class, [
            'label' => 'part.edit.is_favorite'
        ]);

        $builder->add('needsReview', BooleanConstraintType::class, [
           'label' => 'part.edit.needs_review'
        ]);

        $builder->add('mass', NumberConstraintType::class, [
            'label' => 'part.edit.mass'
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'Update',
        ]);
    }
}