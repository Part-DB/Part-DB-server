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
    protected const CHOICES = [
        '' => '',
        '=' => '=',
        '!=' => '!=',
        '<' => '<',
        '>' => '>',
        '<=' => '<=',
        '>=' => '>=',
        'filter.number_constraint.value.operator.BETWEEN' => 'BETWEEN',
    ];

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => NumberConstraint::class,
            'text_suffix' => '', // An suffix which is attached as text-append to the input group. This can for example be used for units

            'min' => null,
            'max' => null,
            'step' => 'any',

            'value1_options' => [], // Options for the first value input
            'value2_options' => [], // Options for the second value input
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('value1', NumberType::class, array_merge_recursive([
            'label' => 'filter.number_constraint.value1',
            'attr' => [
                'placeholder' => 'filter.number_constraint.value1',
                'max' => $options['max'],
                'min' => $options['min'],
                'step' => $options['step'],
            ],
            'required' => false,
            'html5' => true,
        ], $options['value1_options']));

        $builder->add('value2', NumberType::class, array_merge_recursive([
            'label' => 'filter.number_constraint.value2',
            'attr' => [
                'placeholder' => 'filter.number_constraint.value2',
                'max' => $options['max'],
                'min' => $options['min'],
                'step' => $options['step'],
            ],
            'required' => false,
            'html5' => true,
        ], $options['value2_options']));

        $builder->add('operator', ChoiceType::class, [
            'label' => 'filter.number_constraint.operator',
            'choices' => static::CHOICES,
            'required' => false,
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $view->vars['text_suffix'] = $options['text_suffix'];
    }
}