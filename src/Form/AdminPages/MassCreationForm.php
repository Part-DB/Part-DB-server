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

namespace App\Form\AdminPages;


use App\Entity\Base\StructuralDBElement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Security;

class MassCreationForm extends AbstractType
{
    protected $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $data = $options['data'];

        //Disable import if user is not allowed to create elements.
        $entity = new $data['entity_class']();
        $perm_name = "create";
        $disabled = ! $this->security->isGranted($perm_name, $entity);

        $builder
            ->add('lines', TextareaType::class, ['data' => '', 'label' => 'mass_creation.lines',
                'disabled' => $disabled, 'required' => true,
                'attr' => ['placeholder' => 'mass_creation.lines.placeholder', 'rows' => 10]]);
        if ($entity instanceof StructuralDBElement) {
            $builder->
                add('parent', EntityType::class, ['class' => $data['entity_class'], 'choice_label' => 'full_path',
                    'attr' => ['class' => 'selectpicker', 'data-live-search' => true], 'required' => false,
                    'label' => 'parent.label', 'disabled' => $disabled]);
        }

        $builder
            //Buttons
            ->add('create', SubmitType::class, ['label' => 'mass_creation.btn', 'disabled' => $disabled]);
    }
}