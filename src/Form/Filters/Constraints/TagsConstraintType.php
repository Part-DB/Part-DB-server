<?php

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\Part\TagsConstraint;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TagsConstraintType extends AbstractType
{
    protected $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => TagsConstraint::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [
            '' => '',
            'filter.tags_constraint.operator.ANY' => 'ANY',
            'filter.tags_constraint.operator.ALL' => 'ALL',
            'filter.tags_constraint.operator.NONE' => 'NONE'
        ];

        $builder->add('value', SearchType::class, [
            'attr' => [
                'class' => 'tagsinput',
                'data-controller' => 'elements--tagsinput',
                'data-autocomplete' => $this->urlGenerator->generate('typeahead_tags', ['query' => '__QUERY__']),
            ],
            'required' => false,
            'empty_data' => '',
        ]);


        $builder->add('operator', ChoiceType::class, [
            'label' => 'filter.text_constraint.operator',
            'choices' => $choices,
            'required' => false,
        ]);
    }
}