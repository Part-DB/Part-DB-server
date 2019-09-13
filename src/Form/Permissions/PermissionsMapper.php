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


use App\Services\PermissionResolver;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormInterface;

/**
 * This class is a data mapper that maps the permission data from DB (accessed via a PermissionResolver),
 * to TristateCheckboxes and vice versa.
 */
class PermissionsMapper implements DataMapperInterface
{
    protected $resolver;
    protected $inherit;

    public function __construct(PermissionResolver $resolver, bool $inherit = false)
    {
        $this->inherit = $inherit;
        $this->resolver = $resolver;
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
     */
    public function mapDataToForms($viewData, $forms)
    {
        foreach ($forms as $form) {
            if ($this->inherit) {
                $value = $this->resolver->inherit(
                    $viewData,
                    $form->getParent()->getConfig()->getOption('perm_name'),
                    $form->getName()
                ) ?? false;
            } else {
                $value = $this->resolver->dontInherit(
                    $viewData,
                    $form->getParent()->getConfig()->getOption('perm_name'),
                    $form->getName()
                );
            }
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
     */
    public function mapFormsToData($forms, &$viewData)
    {
        if ($this->inherit) {
            throw new \RuntimeException('The permission type is readonly when it is showing read only data!');
        }

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