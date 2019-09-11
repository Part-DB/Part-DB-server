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

namespace App\Form\Permissions;


use App\Form\Type\TriStateCheckboxType;
use App\Services\PermissionResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PermissionType extends AbstractType implements DataMapperInterface
{
    protected $resolver;
    protected $perm_structure;

    public function __construct(PermissionResolver $resolver)
    {
        $this->resolver = $resolver;
        $this->perm_structure = $resolver->getPermissionStructure();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('perm_name', function (Options $options) {
            return $options['name'];
        });

        $resolver->setDefault('label', function (Options $options) {
            if (!empty($this->perm_structure['perms'][$options['perm_name']]['label'])) {
                return $this->perm_structure['perms'][$options['perm_name']]['label'];
            }

            return $options['name'];
        });

        $resolver->setDefault('multi_checkbox', function (Options $options) {
            return !$options['disabled'];
        });

        $resolver->setDefaults([
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $operations = $this->perm_structure['perms'][$options['perm_name']]['operations'];

        foreach ($operations as $key => $operation) {
            $builder->add($key, TriStateCheckboxType::class, [
                'required' => false,
                'mapped' => false,
                'label' => $operation['label'] ?? null,
                'disabled' => $options['disabled']
            ]);
        }

        $builder->setDataMapper($this);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['multi_checkbox'] = $options['multi_checkbox'];
    }

    /**
     * Maps the view data of a compound form to its children.
     *
     * The method is responsible for calling {@link FormInterface::setData()}
     * on the children of compound forms, defining their underlying model data.
     *
     * @param mixed $viewData View data of the compound form being initialized
     * @param FormInterface[]|\Traversable $forms A list of {@link FormInterface} instances
     *
     * @throws Exception\UnexpectedTypeException if the type of the data parameter is not supported
     */
    public function mapDataToForms($viewData, $forms)
    {
        foreach ($forms as $form) {
            $value = $this->resolver->dontInherit(
                $viewData,
                $form->getParent()->getConfig()->getOption('perm_name'),
                $form->getName()
            );
            $form->setData($value);
        }
    }

    /**
     * Maps the model data of a list of children forms into the view data of their parent.
     *
     * This is the internal cascade call of FormInterface::submit for compound forms, since they
     * cannot be bound to any input nor the request as scalar, but their children may:
     *
     *     $compoundForm->submit($arrayOfChildrenViewData)
     *     // inside:
     *     $childForm->submit($childViewData);
     *     // for each entry, do the same and/or reverse transform
     *     $this->dataMapper->mapFormsToData($compoundForm, $compoundInitialViewData)
     *     // then reverse transform
     *
     * When a simple form is submitted the following is happening:
     *
     *     $simpleForm->submit($submittedViewData)
     *     // inside:
     *     $this->viewData = $submittedViewData
     *     // then reverse transform
     *
     * The model data can be an array or an object, so this second argument is always passed
     * by reference.
     *
     * @param FormInterface[]|\Traversable $forms A list of {@link FormInterface} instances
     * @param mixed $viewData The compound form's view data that get mapped
     *                                               its children model data
     *
     * @throws Exception\UnexpectedTypeException if the type of the data parameter is not supported
     */
    public function mapFormsToData($forms, &$viewData)
    {
        foreach ($forms as $form) {
            $value = $form->getData();
            $this->resolver->setPermission(
                $viewData,
                $form->getParent()->getConfig()->getOption('perm_name'),
                $form->getName(),
                $value
            );
        }
    }
}