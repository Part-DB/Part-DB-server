<?php

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\ChoiceConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChoiceConstraintType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('choices');
        $resolver->setAllowedTypes('choices', 'array');

        $resolver->setDefaults([
            'compound' => true,
            'data_class' => ChoiceConstraint::class,
        ]);

    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [
            '' => '',
            'filter.choice_constraint.operator.ANY' => 'ANY',
            'filter.choice_constraint.operator.NONE' => 'NONE',
        ];

        $builder->add('operator', ChoiceType::class, [
            'choices' => $choices,
            'required' => false,
        ]);

        $builder->add('value', ChoiceType::class, [
            'choices' => $options['choices'],
            'required' => false,
            'multiple' => true,
            'attr' => [
                'data-controller' => 'elements--select-multiple',
            ]
        ]);
    }

}