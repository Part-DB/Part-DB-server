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

'use strict';

import TristateCheckbox from "./lib/TristateCheckbox";

class TristateHelper {
    constructor() {
        this.registerTriStateCheckboxes();
    }

    registerTriStateCheckboxes() {
        //Initialize tristate checkboxes and if needed the multicheckbox functionality

        const listener = () => {

            const tristates = document.querySelectorAll("input.tristate");

            tristates.forEach(tristate => {
               TristateCheckbox.getInstance(tristate);
            });


            //Register multi checkboxes in permission tables
            const multicheckboxes = document.querySelectorAll("input.permission_multicheckbox");
            multicheckboxes.forEach(multicheckbox => {
               multicheckbox.addEventListener("change", (event) => {
                    const newValue =  TristateCheckbox.getInstance(event.target).state;
                    const row = event.target.closest("tr");

                    //Find all tristate checkboxes in the same row and set their state to the new value
                    const tristateCheckboxes = row.querySelectorAll("input.tristate");
                    tristateCheckboxes.forEach(tristateCheckbox => {
                        TristateCheckbox.getInstance(tristateCheckbox).state = newValue;
                    });
               });
            });
        }

        document.addEventListener("turbo:load", listener);
        document.addEventListener("turbo:render", listener);
    }
}

export default new TristateHelper();