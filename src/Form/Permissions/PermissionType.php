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

namespace App\Form\Permissions;

use App\Form\Type\TriStateCheckboxType;
use App\Services\UserSystem\PermissionManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PermissionType extends AbstractType
{
    protected PermissionManager $resolver;
    protected array $perm_structure;

    public function __construct(PermissionManager $resolver)
    {
        $this->resolver = $resolver;
        $this->perm_structure = $resolver->getPermissionStructure();
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('perm_name', static function (Options $options) {
            return $options['name'];
        });

        $resolver->setDefault('label', function (Options $options) {
            if (!empty($this->perm_structure['perms'][$options['perm_name']]['label'])) {
                return $this->perm_structure['perms'][$options['perm_name']]['label'];
            }

            return $options['name'];
        });

        $resolver->setDefault('multi_checkbox', static function (Options $options) {
            return !$options['disabled'];
        });

        $resolver->setDefaults([
            'inherit' => false,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $operations = $this->perm_structure['perms'][$options['perm_name']]['operations'];

        foreach ($operations as $key => $operation) {
            $builder->add($key, TriStateCheckboxType::class, [
                'required' => false,
                'mapped' => false,
                'label' => $operation['label'] ?? null,
                'disabled' => $options['disabled'],
                'attr' => [
                    'class' => 'permission-checkbox tristate',
                ],
                'label_attr' => [
                    'class' => 'checkbox-inline opacity-100',
                ],
            ]);
        }

        $builder->setDataMapper(new PermissionsMapper($this->resolver, $options['inherit']));
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['multi_checkbox'] = $options['multi_checkbox'];
    }
}
