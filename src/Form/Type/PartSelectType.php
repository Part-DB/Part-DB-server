<?php

namespace App\Form\Type;

use App\Entity\Parts\Part;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PartSelectType extends AbstractType
{
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'class' => Part::class,
            'choice_label' => 'name',
            'placeholder' => 'None'
        ]);

        $resolver->setDefaults([
            'attr' => [
                'data-controller' => 'elements--part-select',
                'data-autocomplete' => $this->urlGenerator->generate('typeahead_parts', ['query' => '__QUERY__']),
                //Disable browser autocomplete
                'autocomplete' => 'off',
            ],
        ]);

        $resolver->setDefaults(['choices' => []]);

        $resolver->setDefaults([
            'choice_attr' => ChoiceList::attr($this, function (?Part $part) {
                return $part ? [
                    //'data-description' => $part->getDescription(),
                    //'data-category' => $part->getCategory() ? $part->getCategory()->getName() : '',
                ] : [];
            })
        ]);
    }

    public function getParent()
    {
        return EntityType::class;
    }
}