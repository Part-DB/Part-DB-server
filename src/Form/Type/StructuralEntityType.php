<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Form\Type;

use App\Entity\Base\AbstractNamedDBElement;
use App\Form\Type\Helper\StructuralEntityChoiceHelper;
use App\Form\Type\Helper\StructuralEntityChoiceLoader;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This class provides a choice form type similar to EntityType, with the difference, that the tree structure
 * of the StructuralDBElementRepository will be shown to user.
 */
class StructuralEntityType extends AbstractType
{
    public function __construct(protected EntityManagerInterface $em, protected NodesListBuilder $builder, protected TranslatorInterface $translator, protected StructuralEntityChoiceHelper $choice_helper)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event) {
            //When the data starts with "$%$", we assume that the user entered a new element.
            //In that case we add the new element to our choice_loader

            $data = $event->getData();
            if (is_string($data) && str_starts_with($data, '$%$')) {
                //Extract the real name from the data
                $data = substr($data, 3);
            } else {
                return;
            }

            $form = $event->getForm();
            $options = $form->getConfig()->getOptions();
            $choice_loader = $options['choice_loader'];
            if ($choice_loader instanceof StructuralEntityChoiceLoader) {
                $choice_loader->setAdditionalElement($data);
            }
        });

        $builder->addModelTransformer(new CallbackTransformer(
            fn($value) => $this->modelTransform($value, $options), fn($value) => $this->modelReverseTransform($value, $options)));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['class']);
        $resolver->setDefaults([
            'allow_add' => false,
            'show_fullpath_in_subtext' => true, //When this is enabled, the full path will be shown in subtext
            'subentities_of' => null,   //Only show entities with the given parent class
            'disable_not_selectable' => false,  //Disable entries with not selectable property
            'choice_value' => fn(?AbstractNamedDBElement $element) => $this->choice_helper->generateChoiceValue($element), //Use the element id as option value and for comparing items
            'choice_loader' => fn(Options $options) => new StructuralEntityChoiceLoader($options, $this->builder, $this->em),
            'choice_label' => fn(Options $options) => fn($choice, $key, $value) => $this->choice_helper->generateChoiceLabel($choice),
            'choice_attr' => fn(Options $options) => fn($choice, $key, $value) => $this->choice_helper->generateChoiceAttr($choice, $options),
            'group_by' => fn(AbstractNamedDBElement $element) => $this->choice_helper->generateGroupBy($element),
            'choice_translation_domain' => false, //Don't translate the entity names
        ]);

        //Set the constraints for the case that allow to add is enabled (we then have to check that the new element is valid)
        $resolver->setNormalizer('constraints', function (Options $options, $value) {
            if ($options['allow_add']) {
                $value[] = new Valid();
            }

            return $value;
        });

        $resolver->setDefault('empty_message', null);

        $resolver->setDefault('controller', 'elements--structural-entity-select');

        //Options for DTO values
        $resolver->setDefault('dto_value', null);
        $resolver->setAllowedTypes('dto_value', ['null', 'string']);
        //If no help text is explicitly set, we use the dto value as help text and show it as html
        $resolver->setDefault('help', function (Options $options) {
            return $this->dtoText($options['dto_value']);
        });
        $resolver->setDefault('help_html', function (Options $options) {
            return $options['dto_value'] !== null;
        });

        $resolver->setDefault('attr', function (Options $options) {
            $tmp = [
                'data-controller' => $options['controller'],
                'data-allow-add' => $options['allow_add'] ? 'true' : 'false',
                'data-add-hint' => $this->translator->trans('entity.select.add_hint'),
            ];
            if ($options['empty_message']) {
                $tmp['data-empty-message'] = $options['empty_message'];
            }

            return $tmp;
        });
    }

    private function dtoText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $result = '<b>' . $this->translator->trans('info_providers.form.help_prefix') . ':</b> ';

        return $result . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ;
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function modelTransform($value, array $options)
    {
        $choice_loader = $options['choice_loader'];
        if ($choice_loader instanceof StructuralEntityChoiceLoader) {
            $choice_loader->setStartingElement($value);
        }

        return $value;
    }

    public function modelReverseTransform($value, array $options)
    {
        /* This step is important in combination with the caching!
           The elements deserialized from cache, are not known to Doctrine ORM anymore, so doctrine thinks,
           that the entity has changed (and so throws an exception about non-persisted entities).
           This function just retrieves a fresh copy of the entity from database, so doctrine detect correctly that no
           change happened.
           The performance impact of this should be very small in comparison of the boost, caused by the caching.
        */

        if (null === $value) {
            return null;
        }

        //If the value is already in the db, retrieve it freshly
        if ($value->getID()) {
            return $this->em->find($options['class'], $value->getID());
        }

        //Otherwise just return the value
        return $value;
    }
}
