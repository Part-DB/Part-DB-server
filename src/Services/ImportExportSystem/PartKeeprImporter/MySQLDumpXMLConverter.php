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
namespace App\Services\ImportExportSystem\PartKeeprImporter;

class MySQLDumpXMLConverter
{

    /**
     * Converts a MySQL dump XML file to an associative array structure in the following form
     * [
     *    'table_name' => [
     *       [
     *         'column_name' => 'value',
     *        'column_name' => 'value',
     *       ...
     *      ],
     *     [
     *        'column_name' => 'value',
     *       'column_name' => 'value',
     *     ...
     *    ],
     *   ...
     * ],
     *
     * @param  string  $xml_string The XML string to convert
     * @return array The associative array structure
     */
    public function convertMySQLDumpXMLDataToArrayStructure(string $xml_string): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xml_string);

        //Check that the root node is a <mysqldump> node
        $root = $dom->documentElement;
        if ($root->nodeName !== 'mysqldump') {
            throw new \InvalidArgumentException('The given XML string is not a valid MySQL dump XML file!');
        }

        //Get all <database> nodes (there must be exactly one)
        $databases = $root->getElementsByTagName('database');
        if ($databases->length !== 1) {
            throw new \InvalidArgumentException('The given XML string is not a valid MySQL dump XML file!');
        }

        //Get the <database> node
        $database = $databases->item(0);

        //Get all <table_data> nodes
        $tables = $database->getElementsByTagName('table_data');
        $table_data = [];

        //Iterate over all <table> nodes and convert them to arrays
        foreach ($tables as $table) {
            //Normalize the table name to lowercase. On Linux filesystems the tables sometimes contain uppercase letters
            //However we expect the table names to be lowercase in the further steps
            $table_name = strtolower($table->getAttribute('name'));
            $table_data[$table_name] = $this->convertTableToArray($table);
        }

        return $table_data;
    }

    private function convertTableToArray(\DOMElement $table): array
    {
        $table_data = [];

        //Get all <row> nodes
        $rows = $table->getElementsByTagName('row');

        //Iterate over all <row> nodes and convert them to arrays
        foreach ($rows as $row) {
            $table_data[] = $this->convertTableRowToArray($row);
        }

        return $table_data;
    }

    private function convertTableRowToArray(\DOMElement $table_row): array
    {
        $row_data = [];

        //Get all <field> nodes
        $fields = $table_row->getElementsByTagName('field');

        //Iterate over all <field> nodes
        foreach ($fields as $field) {
            $row_data[$field->getAttribute('name')] = $field->nodeValue;
        }

        return $row_data;
    }
}
