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

declare(strict_types=1);

namespace App\Form\InfoProviderSystem;

use App\Entity\Parts\Part;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BulkProviderSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $parts = $options['parts'];

        $builder->add('part_configurations', CollectionType::class, [
            'entry_type' => PartProviderConfigurationType::class,
            'entry_options' => [
                'label' => false,
            ],
            'allow_add' => false,
            'allow_delete' => false,
            'label' => false,
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'info_providers.bulk_search.submit'
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'parts' => [],
        ]);
        $resolver->setRequired('parts');
    }

    private function getDefaultSearchField(Part $part): string
    {
        // Default to MPN if available, otherwise name
        return $part->getManufacturerProductNumber() ? 'mpn' : 'name';
    }
}