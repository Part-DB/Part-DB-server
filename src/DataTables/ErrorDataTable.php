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

namespace App\DataTables;

use App\DataTables\Column\RowClassColumn;
use App\Entity\Parts\Part;
use Omines\DataTablesBundle\Adapter\ArrayAdapter;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableFactory;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ErrorDataTable implements DataTableTypeInterface
{
    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setRequired('errors');
        $optionsResolver->setAllowedTypes('errors', ['array', 'string']);
        $optionsResolver->setNormalizer('errors', function (OptionsResolver $optionsResolver, $errors) {
            if (is_string($errors)) {
                $errors = [$errors];
            }

            return $errors;
        });
    }

    public function configure(DataTable $dataTable, array $options)
    {
        $optionsResolver = new OptionsResolver();
        $this->configureOptions($optionsResolver);
        $options = $optionsResolver->resolve($options);

        $dataTable
            ->add('dont_matter_we_only_set_color', RowClassColumn::class, [
                'render' => function ($value, $context) {
                    return 'table-warning';
                },
            ])

            ->add('error', TextColumn::class, [
                'label' => 'error_table.error',
                'render' => function ($value, $context) {
                    return '<i class="fa-solid fa-triangle-exclamation fa-fw"></i> ' . $value;
                },
            ])
        ;

        //Build the array containing data
        $data = [];
        foreach ($options['errors'] as $error) {
            $data[] = ['error' => $error];
        }

        $dataTable->createAdapter(ArrayAdapter::class, $data);
    }

    public static function errorTable(DataTableFactory $dataTableFactory, Request $request, $errors): Response
    {
        $error_table = $dataTableFactory->createFromType(self::class, ['errors' => $errors]);
        $error_table->handleRequest($request);
        return $error_table->getResponse();
    }
}