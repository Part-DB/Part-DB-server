<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

use App\Services\InfoProviderSystem\FileContentExtractor;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotNull;

class FromFileFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'info_providers.from_file.file.label',
            'help' => 'info_providers.from_file.file.help',
            'required' => true,
            'attr' => [
                'accept' => '.pdf,.md,.txt',
            ],
            'constraints' => [
                new NotNull(),
                new File(maxSize: '50M', mimeTypes: FileContentExtractor::ALLOWED_MIME_TYPES),
            ],
        ]);

        $builder->add('context', TextareaType::class, [
            'label' => 'info_providers.from_file.context.label',
            'help' => 'info_providers.from_file.context.help',
            'required' => false,
            'empty_data' => null,
            'attr' => [
                'rows' => 2,
                'placeholder' => 'info_providers.from_file.context.placeholder',
            ],
            'constraints' => [
                new Length(max: 2000),
            ],
        ]);

        $builder->add('no_cache', CheckboxType::class, [
            'label' => 'info_providers.from_url.no_cache',
            'required' => false,
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'info_providers.from_file.submit',
        ]);
    }
}
