<?php

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\EntityConstraint;
use App\Form\Type\StructuralEntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StructuralEntityConstraintType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' =>  EntityConstraint::class,
            'text_suffix' => '', // An suffix which is attached as text-append to the input group. This can for example be used for units
        ]);

        $resolver->setRequired('entity_class');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [
            '' => '',
            'filter.entity_constraint.operator.EQ' => '=',
            'filter.entity_constraint.operator.NEQ' => '!=',
            'filter.entity_constraint.operator.INCLUDING_CHILDREN' => 'INCLUDING_CHILDREN',
            'filter.entity_constraint.operator.EXCLUDING_CHILDREN' => 'EXCLUDING_CHILDREN',
        ];

        $builder->add('value', StructuralEntityType::class, [
            'class' => $options['entity_class'],
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