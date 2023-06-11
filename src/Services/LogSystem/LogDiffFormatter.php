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

namespace App\Services\LogSystem;

use Jfcherng\Diff\DiffHelper;

class LogDiffFormatter
{
    /**
     * Format the diff between the given data, depending on the type of the data.
     * If the diff is not possible, an empty string is returned.
     * @param $old_data
     * @param $new_data
     */
    public function formatDiff($old_data, $new_data): string
    {
        if (is_string($old_data) && is_string($new_data)) {
            return $this->diffString($old_data, $new_data);
        }

        if (is_numeric($old_data) && is_numeric($new_data)) {
            return $this->diffNumeric($old_data, $new_data);
        }

        return '';
    }

    private function diffString(string $old_data, string $new_data): string
    {
        return DiffHelper::calculate($old_data, $new_data, 'Combined',
            [ //Diff options
                'context' => 2,
            ],
            [ //Render options
                'detailLevel' => 'char',
                'showHeader' => false,
            ]);
    }

    private function diffNumeric($old_data, $new_data): string
    {
        if ((!is_numeric($old_data)) || (!is_numeric($new_data))) {
            throw new \InvalidArgumentException('The given data is not numeric.');
        }

        $difference = $new_data - $old_data;

        //Positive difference
        if ($difference > 0) {
            return sprintf('<span class="text-success">+%s</span>', $difference);
        } elseif ($difference < 0) {
            return sprintf('<span class="text-danger">%s</span>', $difference);
        } else {
            return sprintf('<span class="text-muted">%s</span>', $difference);
        }
    }
}