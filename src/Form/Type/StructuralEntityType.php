<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Form\Type;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This class provides a choice form type similar to EntityType, with the difference, that the tree structure
 * of the StructuralDBElementRepository will be shown to user.
 */
class StructuralEntityType extends AbstractType
{
    protected EntityManagerInterface $em;

    /**
     * @var NodesListBuilder
     */
    protected $builder;

    public function __construct(EntityManagerInterface $em, NodesListBuilder $builder)
    {
        $this->em = $em;
        $this->builder = $builder;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
                                          function ($value) use ($options) {
                                              return $this->transform($value, $options);
                                          }, function ($value) use ($options) {
                                              return $this->reverseTransform($value, $options);
                                          }));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['class']);
        $resolver->setDefaults([
            'show_fullpath_in_subtext' => true, //When this is enabled, the full path will be shown in subtext
            'subentities_of' => null,   //Only show entities with the given parent class
            'disable_not_selectable' => false,  //Disable entries with not selectable property
            'choice_value' => 'id', //Use the element id as option value and for comparing items
            'choice_loader' => function (Options $options) {
                return new CallbackChoiceLoader(function () use ($options) {
                    return $this->getEntries($options);
                });
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
            'choice_translation_domain' => false, //Don't translate the entity names
        ]);

        $resolver->setDefault('empty_message', null);

        $resolver->setDefault('controller', 'elements--selectpicker');

        $resolver->setDefault('attr', static function (Options $options) {
            $tmp = [
                'data-controller' => $options['controller'],
                'data-live-search' => true,
                'title' => 'selectpicker.nothing_selected',
            ];
            if ($options['empty_message']) {
                $tmp['data-none-Selected-Text'] = $options['empty_message'];
                $tmp['title'] = $options['empty_message'];
            }

            return $tmp;
        });
    }

    /**
     * Gets the entries from database and return an array of them.
     */
    public function getEntries(Options $options): array
    {
        return $this->builder->typeToNodesList($options['class'], null);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        //Allow HTML in labels. You must override the 'choice_widget_options' block, so that can work
        //See extendedBootstrap4_layout.html.twig for that...
        $view->vars['use_html_in_labels'] = true;

        parent::buildView($view, $form, $options);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    /**
     *  Transforms a value from the original representation to a transformed representation.
     *
     *  This method is called when the form field is initialized with its default data, on
     *  two occasions for two types of transformers:
     *
     *  1. Model transformers which normalize the model data.
     *     This is mainly useful when the same form type (the same configuration)
     *     has to handle different kind of underlying data, e.g The DateType can
     *     deal with strings or \DateTime objects as input.
     *
     *  2. View transformers which adapt the normalized data to the view format.
     *     a/ When the form is simple, the value returned by convention is used
     *        directly in the view and thus can only be a string or an array. In
     *        this case the data class should be null.
     *
     *     b/ When the form is compound the returned value should be an array or
     *        an object to be mapped to the children. Each property of the compound
     *        data will be used as model data by each child and will be transformed
     *        too. In this case data class should be the class of the object, or null
     *        when it is an array.
     *
     *  All transformers are called in a configured order from model data to view value.
     *  At the end of this chain the view data will be validated against the data class
     *  setting.
     *
     *  This method must be able to deal with empty values. Usually this will
     *  be NULL, but depending on your implementation other empty values are
     *  possible as well (such as empty strings). The reasoning behind this is
     *  that data transformers must be chainable. If the transform() method
     *  of the first data transformer outputs NULL, the second must be able to
     *  process that value.
     *
     * @param mixed $value The value in the original representation
     *
     * @return mixed The value in the transformed representation
     *
     * @throws TransformationFailedException when the transformation fails
     */
    public function transform($value, array $options)
    {
        return $value;
    }

    /**
     *  Transforms a value from the transformed representation to its original
     *  representation.
     *
     *  This method is called when {@link Form::submit()} is called to transform the requests tainted data
     *  into an acceptable format.
     *
     *  The same transformers are called in the reverse order so the responsibility is to
     *  return one of the types that would be expected as input of transform().
     *
     *  This method must be able to deal with empty values. Usually this will
     *  be an empty string, but depending on your implementation other empty
     *  values are possible as well (such as NULL). The reasoning behind
     *  this is that value transformers must be chainable. If the
     *  reverseTransform() method of the first value transformer outputs an
     *  empty string, the second value transformer must be able to process that
     *  value.
     *
     *  By convention, reverseTransform() should return NULL if an empty string
     *  is passed.
     *
     * @param mixed $value The value in the transformed representation
     *
     * @return mixed The value in the original representation
     *
     * @throws TransformationFailedException when the transformation fails
     */
    public function reverseTransform($value, array $options)
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

        return $this->em->find($options['class'], $value->getID());
    }

    /**
     * This generates the HTML code that will be rendered by selectpicker
     * @return string
     */
    protected function getChoiceContent(AbstractStructuralDBElement $choice, $key, $value, $options): string
    {
        $html = "";

        //Add element name, use a class as whitespace which hides when not used in dropdown list
        $html .= $this->getElementNameWithLevelWhitespace($choice, $options, '<span class="picker-level"></span>');

        if ($options['show_fullpath_in_subtext'] && null !== $choice->getParent()) {
            $html .= '<span class="ms-3 badge rounded-pill bg-secondary float-end"><i class="fa-solid fa-folder-tree"></i>&nbsp;' . trim(htmlspecialchars($choice->getParent()->getFullPath())) . '</span>';
        }

        if ($choice instanceof AttachmentType && !empty($choice->getFiletypeFilter())) {
            $html .= '<span class="ms-3 badge bg-warning"><i class="fa-solid fa-file-circle-exclamation"></i>&nbsp;' . trim(htmlspecialchars($choice->getFiletypeFilter())) . '</span>';
        }

        return $html;
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

        //Add the HTML content that will be shown finally in the selectpicker
        $tmp += ['data-content' => $this->getChoiceContent($choice, $key, $value, $options)];

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
        //Just for compatibility reasons for the case selectpicker should not work. The real value is generated in the getChoiceContent() method
        return $this->getElementNameWithLevelWhitespace($choice, $options, " ");
    }
}
