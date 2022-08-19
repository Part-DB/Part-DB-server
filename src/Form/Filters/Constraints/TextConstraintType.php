<?php

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\TextConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TextConstraintType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => TextConstraint::class,
            'text_suffix' => '', // An suffix which is attached as text-append to the input group. This can for example be used for units
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [
            '' => '',
            'filter.text_constraint.value.operator.EQ' => '=',
            'filter.text_constraint.value.operator.NEQ' => '!=',
            'filter.text_constraint.value.operator.STARTS' => 'STARTS',
            'filter.text_constraint.value.operator.ENDS' => 'ENDS',
            'filter.text_constraint.value.operator.CONTAINS' => 'CONTAINS',
            'filter.text_constraint.value.operator.LIKE' => 'LIKE',
            'filter.text_constraint.value.operator.REGEX' => 'REGEX',
        ];

        $builder->add('value', SearchType::class, [
            'attr' => [
                'placeholder' => 'filter.text_constraint.value',
            ],
            'required' => false,
        ]);


        $builder->add('operator', ChoiceType::class, [
            'label' => 'filter.text_constraint.operator',
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