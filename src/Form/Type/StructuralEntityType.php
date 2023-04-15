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
    protected EntityManagerInterface $em;
    protected TranslatorInterface $translator;
    protected StructuralEntityChoiceHelper $choice_helper;

    /**
     * @var NodesListBuilder
     */
    protected NodesListBuilder $builder;

    public function __construct(EntityManagerInterface $em, NodesListBuilder $builder, TranslatorInterface $translator, StructuralEntityChoiceHelper $choice_helper)
    {
        $this->em = $em;
        $this->builder = $builder;
        $this->translator = $translator;
        $this->choice_helper = $choice_helper;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (PreSubmitEvent $event) {
            //When the data contains non-digit characters, we assume that the user entered a new element.
            //In that case we add the new element to our choice_loader

            $data = $event->getData();
            if (null === $data || !is_string($data) || $data === "" || ctype_digit($data)) {
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
            function ($value) use ($options) {
                return $this->modelTransform($value, $options);
            }, function ($value) use ($options) {
            return $this->modelReverseTransform($value, $options);
        }));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['class']);
        $resolver->setDefaults([
            'allow_add' => false,
            'show_fullpath_in_subtext' => true, //When this is enabled, the full path will be shown in subtext
            'subentities_of' => null,   //Only show entities with the given parent class
            'disable_not_selectable' => false,  //Disable entries with not selectable property
            'choice_value' => function (?AbstractNamedDBElement $element) {
                return $this->choice_helper->generateChoiceValue($element);
            }, //Use the element id as option value and for comparing items
            'choice_loader' => function (Options $options) {
                return new StructuralEntityChoiceLoader($options, $this->builder, $this->em);
            },
            'choice_label' => function (Options $options) {
                return function ($choice, $key, $value) {
                    return $this->choice_helper->generateChoiceLabel($choice);
                };
            },
            'choice_attr' => function (Options $options) {
                return function ($choice, $key, $value) use ($options) {
                    return $this->choice_helper->generateChoiceAttr($choice, $options);
                };
            },
            'group_by' => function (AbstractNamedDBElement $element) {
                return $this->choice_helper->generateGroupBy($element);
            },
            'choice_translation_domain' => false, //Don't translate the entity names
        ]);

        //Set the constraints for the case that allow add is enabled (we then have to check that the new element is valid)
        $resolver->setNormalizer('constraints', function (Options $options, $value) {
            if ($options['allow_add']) {
                $value[] = new Valid();
            }

            return $value;
        });

        $resolver->setDefault('empty_message', null);

        $resolver->setDefault('controller', 'elements--structural-entity-select');

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


    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function modelTransform($value, array $options)
    {
        return $value;
    }

    public function modelReverseTransform($value, array $options)
    {
        /* This step is important in combination with the caching!
           The elements deserialized from cache, are not known to Doctrinte ORM any more, so doctrine thinks,
           that the entity has changed (and so throws an exception about non-persited entities).
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
