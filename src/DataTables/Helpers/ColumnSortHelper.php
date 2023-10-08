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


namespace App\DataTables\Helpers;

use Omines\DataTablesBundle\DataTable;
use Psr\Log\LoggerInterface;

class ColumnSortHelper
{
    private array $columns = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Add a new column which can be sorted and visibility controlled by the user. The basic syntax is similar to
     * the DataTable add method, but with additional options.
     * @param  string  $name
     * @param  string  $type
     * @param  array  $options
     * @param  string|null  $alias If an alias is set here, the column will be available under this alias in the config
     * string instead of the name.
     * @param  bool  $visibility_configurable If set to false, this column can not be visibility controlled by the user
     * @return $this
     */
    public function add(string $name, string $type, array $options = [], ?string $alias = null,
        bool $visibility_configurable = true): self
    {
        //Alias allows us to override the name of the column in the env variable
        $this->columns[$alias ?? $name] = [
            'name' => $name,
            'type' => $type,
            'options' => $options,
            'visibility_configurable' => $visibility_configurable
        ];

        return $this;
    }

    /**
     * Remove all columns saved inside this helper
     * @return void
     */
    public function reset(): void
    {
        $this->columns = [];
    }

    /**
     * Apply the visibility configuration to the given DataTable and configure the columns.
     * @param  DataTable  $dataTable
     * @param  string|array  $visible_columns Either a list or a comma separated string of column names, which should
     * be visible by default. If a column is not listed here, it will be hidden by default.
     * @return void
     */
    public function applyVisibilityAndConfigureColumns(DataTable $dataTable, string|array $visible_columns,
        string $config_var_name): void
    {
        //If the config is given as a string, convert it to an array first
        if (!is_array($visible_columns)) {
            $visible_columns = array_map(trim(...), explode(",", $visible_columns));
        }

        $processed_columns = [];

        //First add all columns which visibility is not configurable
        foreach ($this->columns as $col_id => $col_data) {
            if (!$col_data['visibility_configurable']) {
                $this->addColumnEntry($dataTable, $this->columns[$col_id], null);
                $processed_columns[] = $col_id;
            }
        }

        //Afterwards the columns, which should be visible by default
        foreach ($visible_columns as $col_id) {
            if (!isset($this->columns[$col_id]) || !$this->columns[$col_id]['visibility_configurable']) {
                $this->logger->warning("Configuration option $config_var_name specify invalid column '$col_id'. Column is skipped.");
                continue;
            }

            if (in_array($col_id, $processed_columns, true)) {
                $this->logger->warning("Configuration option $config_var_name specify column '$col_id' multiple time. Only first occurrence is used.");
                continue;
            }
            $this->addColumnEntry($dataTable, $this->columns[$col_id], true);
            $processed_columns[] = $col_id;
        }

        //and the remaining non-visible columns
        foreach ($this->columns as $col_id => $col_data) {
            if (in_array($col_id, $processed_columns)) {
                // column already processed
                continue;
            }
            $this->addColumnEntry($dataTable, $this->columns[$col_id], false);
            $processed_columns[] = $col_id;
        }
    }

    private function addColumnEntry(DataTable $dataTable, array $entry, ?bool $visible): void
    {
        $options = $entry['options'] ?? [];
        if (!is_null($visible)) {
            $options["visible"] = $visible;
        }
        $dataTable->add($entry['name'], $entry['type'], $options);
    }
}