<?php

namespace App\Form\ProjectSystem;

use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Form\Type\BigDecimalNumberType;
use App\Form\Type\CurrencyEntityType;
use App\Form\Type\PartSelectType;
use App\Form\Type\RichTextEditorType;
use App\Form\Type\SIUnitType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectBOMEntryType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {


        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event) {
            $form = $event->getForm();
            /** @var ProjectBOMEntry $data */
            $data = $event->getData();

            $form->add('quantity', SIUnitType::class, [
                'label' => 'project.bom.quantity',
                'measurement_unit' => $data && $data->getPart() ? $data->getPart()->getPartUnit() : null,
            ]);
        });

        $builder

            ->add('part', PartSelectType::class, [
                'required' => false,
            ])

            ->add('name', TextType::class, [
                'label' => 'project.bom.name',
                'required' => false,
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
            ->add('comment', RichTextEditorType::class, [
                'required' => false,
                'label' => 'project.bom.comment',
                'empty_data' => '',
                'mode' => 'markdown-single_line',
                'attr' => [
                    'rows' => 2,
                ],
            ])
            ->add('price', BigDecimalNumberType::class, [
                'label' => false,
                'required' => false,
                'scale' => 5,
                'html5' => true,
                'attr' => [
                    'min' => 0,
                    'step' => 'any',
                ],
            ])
            ->add('priceCurrency', CurrencyEntityType::class, [
                'required' => false,
                'label' => false,
                'short' => true,
            ])

        ;

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProjectBOMEntry::class,
        ]);
    }
}