<?php

namespace App\Form\ProjectSystem;

use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use Svg\Tag\Text;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectBOMEntryType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder

            ->add('quantity', NumberType::class, [
                'label' => 'project.bom.quantity',
            ])

            ->add('part', EntityType::class, [
                'class' => Part::class,
                'choice_label' => 'name',
                'required' => false,
            ])

            ->add('name', TextType::class, [
                'label' => 'project.bom.name',
                'required' => false,
                'empty_data' => ''
            ])
            ->add('mountnames', TextType::class, [
                'required' => false,
                'label' => 'project.bom.mountnames',
                'empty_data' => '',
                'attr' => [
                    'class' => 'tagsinput',
                    'data-controller' => 'elements--tagsinput',
                ]
            ])
            ->add('comment', TextType::class, [
                'required' => false,
                'label' => 'project.bom.comment',
                'empty_data' => ''
            ])
        ;

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectBOMEntry::class,
        ]);
    }


    public function getBlockPrefix()
    {
        return 'project_bom_entry';
    }
}