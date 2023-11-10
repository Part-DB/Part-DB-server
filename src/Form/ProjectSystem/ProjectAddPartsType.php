<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Form\ProjectSystem;

use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Form\Type\StructuralEntityType;
use App\Validator\Constraints\UniqueObjectCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class ProjectAddPartsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('project', StructuralEntityType::class, [
            'class' => Project::class,
            'required' => true,
            'disabled' => $options['project'] instanceof Project, //If a project is given, disable the field
            'data' => $options['project'],
            'constraints' => [
                new NotNull()
            ]
        ]);
        $builder->add('bom_entries', ProjectBOMEntryCollectionType::class, [
            'entry_options' => [
                'constraints' => [
                    new UniqueEntity(fields: ['part', 'project'], entityClass: ProjectBOMEntry::class, message: 'project.bom_entry.part_already_in_bom'),
                    new UniqueEntity(fields: ['name', 'project'], entityClass: ProjectBOMEntry::class, message: 'project.bom_entry.name_already_in_bom', ignoreNull: true),
                ]
            ],
            'constraints' => [
                new UniqueObjectCollection(fields: ['part'], message: 'project.bom_entry.part_already_in_bom'),
                new UniqueObjectCollection(fields: ['name'], message: 'project.bom_entry.name_already_in_bom'),
            ]
        ]);
        $builder->add('submit', SubmitType::class, ['label' => 'save']);

        //After submit set the project for all bom entries, so that it can be validated properly
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            /** @var Project $project */
            $project = $form->get('project')->getData();
            $bom_entries = $form->get('bom_entries')->getData();

            foreach ($bom_entries as $bom_entry) {
                $bom_entry->setProject($project);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'project' => null,
        ]);

        $resolver->setAllowedTypes('project', ['null', Project::class]);
    }
}