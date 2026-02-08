<?php

declare(strict_types=1);

namespace App\Form\Part\EDA;

use App\Form\Type\TriStateCheckboxType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\Translation\t;

/**
 * Form type for batch editing EDA/KiCad fields on multiple parts at once.
 * Each field has an "apply" checkbox — only checked fields are applied.
 */
class BatchEdaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reference_prefix', TextType::class, [
                'label' => 'eda_info.reference_prefix',
                'required' => false,
                'attr' => ['placeholder' => t('eda_info.reference_prefix.placeholder')],
            ])
            ->add('apply_reference_prefix', CheckboxType::class, [
                'label' => 'batch_eda.apply',
                'required' => false,
                'mapped' => false,
            ])
            ->add('value', TextType::class, [
                'label' => 'eda_info.value',
                'required' => false,
                'attr' => ['placeholder' => t('eda_info.value.placeholder')],
            ])
            ->add('apply_value', CheckboxType::class, [
                'label' => 'batch_eda.apply',
                'required' => false,
                'mapped' => false,
            ])
            ->add('kicad_symbol', KicadFieldAutocompleteType::class, [
                'label' => 'eda_info.kicad_symbol',
                'type' => KicadFieldAutocompleteType::TYPE_SYMBOL,
                'required' => false,
                'attr' => ['placeholder' => t('eda_info.kicad_symbol.placeholder')],
            ])
            ->add('apply_kicad_symbol', CheckboxType::class, [
                'label' => 'batch_eda.apply',
                'required' => false,
                'mapped' => false,
            ])
            ->add('kicad_footprint', KicadFieldAutocompleteType::class, [
                'label' => 'eda_info.kicad_footprint',
                'type' => KicadFieldAutocompleteType::TYPE_FOOTPRINT,
                'required' => false,
                'attr' => ['placeholder' => t('eda_info.kicad_footprint.placeholder')],
            ])
            ->add('apply_kicad_footprint', CheckboxType::class, [
                'label' => 'batch_eda.apply',
                'required' => false,
                'mapped' => false,
            ])
            ->add('visibility', TriStateCheckboxType::class, [
                'label' => 'eda_info.visibility',
            ])
            ->add('apply_visibility', CheckboxType::class, [
                'label' => 'batch_eda.apply',
                'required' => false,
                'mapped' => false,
            ])
            ->add('exclude_from_bom', TriStateCheckboxType::class, [
                'label' => 'eda_info.exclude_from_bom',
            ])
            ->add('apply_exclude_from_bom', CheckboxType::class, [
                'label' => 'batch_eda.apply',
                'required' => false,
                'mapped' => false,
            ])
            ->add('exclude_from_board', TriStateCheckboxType::class, [
                'label' => 'eda_info.exclude_from_board',
            ])
            ->add('apply_exclude_from_board', CheckboxType::class, [
                'label' => 'batch_eda.apply',
                'required' => false,
                'mapped' => false,
            ])
            ->add('exclude_from_sim', TriStateCheckboxType::class, [
                'label' => 'eda_info.exclude_from_sim',
            ])
            ->add('apply_exclude_from_sim', CheckboxType::class, [
                'label' => 'batch_eda.apply',
                'required' => false,
                'mapped' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'batch_eda.submit',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
