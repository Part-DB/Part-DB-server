<?php

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\NumberConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NumberConstraintType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => NumberConstraint::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [
            '=' => '=',
            '!=' => '!=',
            '<' => '<',
            '>' => '>',
            '<=' => '<=',
            '>=' => '>=',
            'BETWEEN' => 'BETWEEN',
        ];

        $builder->add('value1', NumberType::class, [
            'label' => 'filter.number_constraint.value1',
            'attr' => [
                'placeholder' => 'filter.number_constraint.value1',
            ],
            'required' => false,
            'html5' => true,
        ]);
        $builder->add('value2', NumberType::class, [
            'label' => 'filter.number_constraint.value2',
            'attr' => [
                'placeholder' => 'filter.number_constraint.value2',
            ],
            'required' => false,
            'html5' => true,
        ]);
        $builder->add('operator', ChoiceType::class, [
            'label' => 'filter.number_constraint.operator',
            'choices' => $choices,
        ]);
    }
}