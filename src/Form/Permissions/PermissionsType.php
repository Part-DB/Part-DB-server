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
use App\Validator\Constraints\NoLockout;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PermissionsType extends AbstractType
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
        $resolver->setDefaults([
            'show_legend' => true,
            'constraints' => function (Options $options) {
                if (!$options['disabled']) {
                    return [new NoLockout()];
                }
                return [];
            },
            'inherit' => false,
        ]);


    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['show_legend'] = $options['show_legend'];
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $groups = $this->perm_structure['groups'];

        foreach ($groups as $key => $group) {
            $builder->add($key,PermissionGroupType::class, [
                'group_name' => $key,
                'mapped' => false,
                'data' => $builder->getData(),
                'disabled' => $options['disabled'],
                'inherit' => $options['inherit']
            ]);
        }

        $builder->add('blanko', PermissionGroupType::class, [
            'group_name' => '*',
            'label' => 'perm.group.other',
            'mapped' => false,
            'data' => $builder->getData(),
            'disabled' => $options['disabled'],
            'inherit' => $options['inherit']
        ]);
    }
}