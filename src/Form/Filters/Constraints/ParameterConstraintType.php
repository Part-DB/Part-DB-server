<?php

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\Part\ParameterConstraint;
use Svg\Tag\Text;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParameterConstraintType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => ParameterConstraint::class,
            'empty_data' => new ParameterConstraint(),
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

        $builder->add('value_text', TextConstraintType::class, [
            //'required' => false,
        ] );

        $builder->add('value', ParameterValueConstraintType::class, [
        ]);

        /*
         * I am not quite sure why this is needed, but somehow symfony tries to create a new instance of TextConstraint
         * instead of using the existing one for the prototype (or the one from empty data). This fails as the constructor of TextConstraint requires
         * arguments.
         * Ensure that the data is never null, but use an empty ParameterConstraint instead
         */
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();

            if ($data === null) {
                $event->setData(new ParameterConstraint());
            }
        });
    }
}