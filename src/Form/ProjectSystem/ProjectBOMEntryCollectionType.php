<?php

namespace App\Form\ProjectSystem;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectBOMEntryCollectionType extends AbstractType
{
    public function getParent(): string
    {
        return CollectionType::class;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'entry_type' => ProjectBOMEntryType::class,
            'entry_options' => [
                'label' => false,
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'reindex_enable' => true,
            'label' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'project_bom_entry_collection';
    }
}