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

import {Controller} from "@hotwired/stimulus";

/*
 * Define this controller on a checkbox, which should be used as a master to select/deselect all other checkboxes
 * with the same data-multicheck-name attribute.
 */
export default class extends Controller
{
    connect() {
        this.element.addEventListener("change", this.toggleAll.bind(this));
    }

    toggleAll() {
        //Retrieve all checkboxes, which have the same data-multicheck-name attribute as the current checkbox
        const checkboxes = document.querySelectorAll(`input[type="checkbox"][data-multicheck-name="${this.element.dataset.multicheckName}"]`);
        for (let checkbox of checkboxes) {
            checkbox.checked = this.element.checked;
        }
    }
}