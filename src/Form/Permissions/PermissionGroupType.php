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

use App\Services\UserSystem\PermissionManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PermissionGroupType extends AbstractType
{
    protected PermissionManager $resolver;
    protected array $perm_structure;

    public function __construct(PermissionManager $resolver)
    {
        $this->resolver = $resolver;
        $this->perm_structure = $resolver->getPermissionStructure();
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $permissions = $this->perm_structure['perms'];

        foreach ($permissions as $key => $permission) {
            //Check if the permission belongs to our group
            if (isset($permission['group'])) {
                if ($permission['group'] !== $options['group_name']) {
                    continue;
                }
            } elseif ('*' !== $options['group_name']) {
                continue;
            }

            $builder->add($key, PermissionType::class, [
                'perm_name' => $key,
                'label' => $permission['label'] ?? $key,
                'mapped' => false,
                'data' => $builder->getData(),
                'disabled' => $options['disabled'],
                'inherit' => $options['inherit'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('group_name', static function (Options $options) {
            return trim($options['name']);
        });

        $resolver->setDefault('inherit', false);

        $resolver->setDefault('label', function (Options $options) {
            if (!empty($this->perm_structure['groups'][$options['group_name']]['label'])) {
                return $this->perm_structure['groups'][$options['group_name']]['label'];
            }

            return $options['name'];
        });
    }
}
