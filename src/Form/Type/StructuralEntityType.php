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

use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Form\Type\Helper\StructuralEntityChoiceLoader;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\IsNull;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This class provides a choice form type similar to EntityType, with the difference, that the tree structure
 * of the StructuralDBElementRepository will be shown to user.
 */
class StructuralEntityType extends AbstractType
{
    protected EntityManagerInterface $em;
    protected AttachmentURLGenerator $attachmentURLGenerator;
    protected TranslatorInterface $translator;

    /**
     * @var NodesListBuilder
     */
    protected $builder;

    public function __construct(EntityManagerInterface $em, NodesListBuilder $builder, AttachmentURLGenerator $attachmentURLGenerator, TranslatorInterface $translator)
    {
        $this->em = $em;
        $this->builder = $builder;
        $this->attachmentURLGenerator = $attachmentURLGenerator;
        $this->translator = $translator;
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
            'choice_value' => function (?AbstractStructuralDBElement $element) {
                if ($element === null) {
                    return null;
                }

                /**
                 * Do not change the structure below, even when inspection says it can be replaced with a null coalescing operator.
                 * It is important that the value returned here for a existing element is an int, and for a new element a string.
                 * I dont really understand why, but it seems to be important for the choice_loader to work correctly.
                 * So please do not change this!
                 */
                if ($element->getID() === null) {
                    //Must be the same as the separator in the choice_loader, otherwise this will not work!
                    return $element->getFullPath('->');
                }

                return $element->getID();
            }, //Use the element id as option value and for comparing items
            'choice_loader' => function (Options $options) {
                return new StructuralEntityChoiceLoader($options, $this->builder, $this->em);
            },
            'choice_label' => function (Options $options) {
                return function ($choice, $key, $value) use ($options) {
                    return $this->generateChoiceLabels($choice, $key, $value, $options);
                };
            },
            'choice_attr' => function (Options $options) {
                return function ($choice, $key, $value) use ($options) {
                    return $this->generateChoiceAttr($choice, $key, $value, $options);
                };
            },
            'group_by' => function (AbstractStructuralDBElement $element)
            {
                //Show entities that are not added to DB yet separately from other entities
                if ($element->getID() === null) {
                    return $this->translator->trans('entity.select.group.new_not_added_to_DB');
                }

                return null;
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

    protected function generateChoiceAttr(AbstractStructuralDBElement $choice, $key, $value, $options): array
    {
        $tmp = [];

        //Disable attribute if the choice is marked as not selectable
        if ($options['disable_not_selectable'] && $choice->isNotSelectable()) {
            $tmp += ['disabled' => 'disabled'];
        }

        if ($choice instanceof AttachmentType) {
            $tmp += ['data-filetype_filter' => $choice->getFiletypeFilter()];
        }

        $level = $choice->getLevel();
        /** @var AbstractStructuralDBElement|null $parent */
        $parent = $options['subentities_of'];
        if (null !== $parent) {
            $level -= $parent->getLevel() - 1;
        }

        $tmp += [
            'data-level' => $level,
            'data-parent' => $choice->getParent() ? $choice->getParent()->getFullPath() : null,
            'data-path' => $choice->getFullPath('->'),
            'data-image' => $choice->getMasterPictureAttachment() ? $this->attachmentURLGenerator->getThumbnailURL($choice->getMasterPictureAttachment(), 'thumbnail_xs') : null,
        ];

        if ($choice instanceof AttachmentType && !empty($choice->getFiletypeFilter())) {
            $tmp += ['data-filetype_filter' => $choice->getFiletypeFilter()];
        }

        return $tmp;
    }

    protected function getElementNameWithLevelWhitespace(AbstractStructuralDBElement $choice, $options, $whitespace = "&nbsp;&nbsp;&nbsp;"): string
    {
        /** @var AbstractStructuralDBElement|null $parent */
        $parent = $options['subentities_of'];

        /*** @var AbstractStructuralDBElement $choice */
        $level = $choice->getLevel();
        //If our base entity is not the root level, we need to change the level, to get zero position
        if (null !== $options['subentities_of']) {
            $level -= $parent->getLevel() - 1;
        }

        $tmp = str_repeat($whitespace, $level); //Use 3 spaces for intendation
        $tmp .= htmlspecialchars($choice->getName());

        return $tmp;
    }

    protected function generateChoiceLabels(AbstractStructuralDBElement $choice, $key, $value, $options): string
    {
        return $choice->getName();
    }
}
