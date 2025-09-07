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
import TomSelect from "tom-select";

// TODO: Merge with select_controller.js

export default class extends Controller {
    _tomSelect;

    connect() {
        this._tomSelect = new TomSelect(this.element, {
            maxItems: 1000,
            allowEmptyOption: true,
            dropdownParent: 'body',
            plugins: ['remove_button'],
        });
    }

    disconnect() {
        super.disconnect();
        //Destroy the TomSelect instance
        this._tomSelect.destroy();
    }

}
