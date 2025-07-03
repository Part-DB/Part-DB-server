<?php

declare(strict_types=1);

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
namespace App\Form\AssemblySystem;

use App\Entity\AssemblySystem\Assembly;
use App\Entity\AssemblySystem\AssemblyBOMEntry;
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

class AssemblyAddPartsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('assembly', StructuralEntityType::class, [
            'class' => Assembly::class,
            'required' => true,
            'disabled' => $options['assembly'] instanceof Assembly, //If a assembly is given, disable the field
            'data' => $options['assembly'],
            'constraints' => [
                new NotNull()
            ]
        ]);
        $builder->add('bom_entries', AssemblyBOMEntryCollectionType::class, [
            'entry_options' => [
                'constraints' => [
                    new UniqueEntity(fields: ['part'], message: 'assembly.bom_entry.part_already_in_bom',
                        entityClass: AssemblyBOMEntry::class),
                    new UniqueEntity(fields: ['referencedAssembly'], message: 'assembly.bom_entry.assembly_already_in_bom',
                        entityClass: AssemblyBOMEntry::class),
                    new UniqueEntity(fields: ['name'], message: 'assembly.bom_entry.name_already_in_bom',
                        entityClass: AssemblyBOMEntry::class, ignoreNull: true),
                ]
            ],
            'constraints' => [
                new UniqueObjectCollection(message: 'assembly.bom_entry.part_already_in_bom', fields: ['part']),
                new UniqueObjectCollection(message: 'assembly.bom_entry.assembly_already_in_bom', fields: ['referencedAssembly']),
                new UniqueObjectCollection(message: 'assembly.bom_entry.name_already_in_bom', fields: ['name']),
            ]
        ]);
        $builder->add('submit', SubmitType::class, ['label' => 'save']);

        //After submit set the assembly for all bom entries, so that it can be validated properly
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            /** @var Assembly $assembly */
            $assembly = $form->get('assembly')->getData();
            $bom_entries = $form->get('bom_entries')->getData();

            foreach ($bom_entries as $bom_entry) {
                $bom_entry->setAssembly($assembly);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'assembly' => null,
        ]);

        $resolver->setAllowedTypes('assembly', ['null', Assembly::class]);
    }
}
