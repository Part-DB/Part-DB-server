<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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
 *
 */

namespace App\Form\Type;


use App\Entity\Base\StructuralDBElement;
use App\Entity\Parts\Storelocation;
use App\Repository\StructuralDBElementRepository;
use App\Services\TreeBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This class provides a choice form type similar to EntityType, with the difference, that the tree structure
 * of the StructuralDBElementRepository will be shown to user
 * @package App\Form\Type
 */
class StructuralEntityType extends AbstractType
{
    protected $em;
    protected $options;
    /** @var TreeBuilder  */
    protected $builder;

    public function __construct(EntityManagerInterface $em, TreeBuilder $builder)
    {
        $this->em = $em;
        $this->builder = $builder;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(['class']);

        $resolver->setDefaults(['attr' => ['class' => 'selectpicker', 'data-live-search' => true],
            'show_fullpath_in_subtext' => true, //When this is enabled, the full path will be shown in subtext
            'subentities_of' => null,   //Only show entities with the given parent class
            'disable_not_selectable' => false,  //Disable entries with not selectable property
            'choice_loader' => function (Options $options) {
                return new CallbackChoiceLoader(function () use ($options) {
                    return $this->getEntries($options);
                });
            }, 'choice_label' => function ($choice, $key, $value) {
                return $this->generateChoiceLabels($choice, $key, $value);
            }, 'choice_attr' => function ($choice, $key, $value) {
                return $this->generateChoiceAttr($choice, $key, $value);
            }
        ]);
    }

    protected function generateChoiceAttr(StructuralDBElement $choice, $key, $value) : array
    {
        $tmp = array();

        if ($this->options['show_fullpath_in_subtext'] && $choice->getParent() != null) {
            $tmp += ['data-subtext' => $choice->getParent()->getFullPath()];
        }

        //Disable attribute if the choice is marked as not selectable
        if ($this->options['disable_not_selectable'] && $choice->isNotSelectable()) {
            $tmp += ['disabled' => 'disabled'];
        }
        return $tmp;
    }

    protected function generateChoiceLabels(StructuralDBElement $choice, $key, $value) : string
    {
        /** @var StructuralDBElement|null $parent */
        $parent = $this->options['subentities_of'];

        /*** @var StructuralDBElement $choice */
        $level = $choice->getLevel();
        //If our base entity is not the root level, we need to change the level, to get zero position
        if ($this->options['subentities_of'] !== null) {
            $level -= $parent->getLevel() - 1;
        }


        $tmp = str_repeat('&nbsp;&nbsp;&nbsp;', $choice->getLevel()); //Use 3 spaces for intendation
        $tmp .=  htmlspecialchars($choice->getName($parent));
        return $tmp;
    }

    /**
     * Gets the entries from database and return an array of them
     * @param Options $options
     * @return array
     */
    public function getEntries(Options $options) : array
    {
        $this->options = $options;

        $choices = $this->builder->typeToNodesList($options['class'], null);


        /** @var StructuralDBElementRepository $repo */
        /*$repo = $this->em->getRepository($options['class']);
        $choices = $repo->toNodesList(null); */
        return $choices;
    }


    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        //Allow HTML in labels. You must override the 'choice_widget_options' block, so that can work
        //See extendedBootstrap4_layout.html.twig for that...
        $view->vars['use_html_in_labels'] = true;

        parent::buildView($view, $form, $options); // TODO: Change the autogenerated stub
    }

    public function getParent()
    {
        return ChoiceType::class;
    }
}