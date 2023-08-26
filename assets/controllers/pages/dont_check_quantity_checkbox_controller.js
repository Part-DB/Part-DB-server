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

import {Controller} from "@hotwired/stimulus";

/**
 * This controller is used on a checkbox, which toggles the max value of all number input fields
 */
export default class extends Controller {

    _checkbox;

    getCheckbox() {
        if (this._checkbox) {
            return this._checkbox;
        }

        //Find the checkbox inside the controller element
        this._checkbox = this.element.querySelector('input[type="checkbox"]');
        return this._checkbox;
    }

    connect() {
        //Add event listener to the checkbox
        this.getCheckbox().addEventListener('change', this.toggleInputLimits.bind(this));
    }

    toggleInputLimits() {
        //Find all input fields with the data-toggle-input-limits-target="max"
        const inputFields = document.querySelectorAll("input[type='number']");

        inputFields.forEach((inputField) => {
            //Ensure that the input field has either a max or a data-max attribute
            if (!inputField.hasAttribute('max') && !inputField.hasAttribute('data-max')) {
                return;
            }

            //If the checkbox is checked, rename the max attribute to data-max
            if (this.getCheckbox().checked) {
                inputField.setAttribute('data-max', inputField.getAttribute('max'));
                inputField.removeAttribute('max');
            } else {
                //If the checkbox is not checked, rename the data-max attribute back to max
                inputField.setAttribute('max', inputField.getAttribute('data-max'));
                inputField.removeAttribute('data-max');
            }
        });
    }
}