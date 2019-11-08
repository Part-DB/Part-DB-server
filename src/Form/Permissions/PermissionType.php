<?php
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

namespace App\Form\Permissions;

use App\Form\Type\TriStateCheckboxType;
use App\Services\PermissionResolver;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PermissionType extends AbstractType
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
            'inherit' => false,
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
                'disabled' => $options['disabled'],
            ]);
        }

        $builder->setDataMapper(new PermissionsMapper($this->resolver, $options['inherit']));
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['multi_checkbox'] = $options['multi_checkbox'];
    }
}
