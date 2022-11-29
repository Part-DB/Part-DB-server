/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

import DatatablesController from "./datatables_controller.js";

/**
 * This is the datatables controller for log pages, it includes an mechanism to color lines based on their level.
 */
export default class extends DatatablesController {
    _rowCallback(row, data, index) {
        //Check if we have a level, then change color of this row
        if (data.level) {
            let style = "";
            switch (data.level) {
                case "emergency":
                case "alert":
                case "critical":
                case "error":
                    style = "table-danger";
                    break;
                case "warning":
                    style = "table-warning";
                    break;
                case "notice":
                    style = "table-info";
                    break;
            }

            if (style) {
                $(row).addClass(style);
            }
        }
    }
}

