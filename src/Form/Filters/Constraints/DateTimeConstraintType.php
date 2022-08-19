<?php

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\DateTimeConstraint;
use App\DataTables\Filters\Constraints\NumberConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateTimeConstraintType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => DateTimeConstraint::class,
            'text_suffix' => '', // An suffix which is attached as text-append to the input group. This can for example be used for units

            'value1_options' => [], // Options for the first value input
            'value2_options' => [], // Options for the second value input
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [
            '' => '',
            '=' => '=',
            '!=' => '!=',
            '<' => '<',
            '>' => '>',
            '<=' => '<=',
            '>=' => '>=',
            'BETWEEN' => 'BETWEEN',
        ];

        $builder->add('value1', DateTimeType::class, array_merge_recursive([
            'label' => 'filter.datetime_constraint.value1',
            'attr' => [
                'placeholder' => 'filter.datetime_constraint.value1',
            ],
            'required' => false,
            'html5' => true,
            'widget' => 'single_text',
        ], $options['value1_options']));

        $builder->add('value2', DateTimeType::class, array_merge_recursive([
            'label' => 'filter.datetime_constraint.value2',
            'attr' => [
                'placeholder' => 'filter.datetime_constraint.value2',
            ],
            'required' => false,
            'html5' => true,
            'widget' => 'single_text',
        ], $options['value2_options']));

        $builder->add('operator', ChoiceType::class, [
            'label' => 'filter.datetime_constraint.operator',
            'choices' => $choices,
            'required' => false,
        ]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        parent::buildView($view, $form, $options);

        $view->vars['text_suffix'] = $options['text_suffix'];
    }
}