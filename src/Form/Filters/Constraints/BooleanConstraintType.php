<?php

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\BooleanConstraint;
use App\Form\Type\TriStateCheckboxType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BooleanConstraintType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => BooleanConstraint::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('value', TriStateCheckboxType::class, [
            'label' => $options['label'],
            'required' => false,
        ]);
    }
}