<?php

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\Part\ParameterConstraint;
use Svg\Tag\Text;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParameterConstraintType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => ParameterConstraint::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'required' => false,
        ]);

        $builder->add('unit', SearchType::class, [
            'required' => false,
        ]);

        $builder->add('symbol', SearchType::class, [
           'required' => false
        ]);
    }
}