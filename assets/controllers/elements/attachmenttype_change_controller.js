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
 * This controller synchronizes the filetype filters of the file input type with our selected attachment type
 */
export default class extends Controller
{
    _selectInput;
    _fileInput;

    connect() {
        //Find the select input for our attachment form
        this._selectInput = this.element.querySelector('select');
        //Find the file input for our attachment form
        this._fileInput = this.element.querySelector('input[type="file"]');

        this._selectInput.addEventListener('change', this.updateAllowedFiletypes.bind(this));

        //Update file file on load
        this.updateAllowedFiletypes();
    }

    updateAllowedFiletypes() {
        let selected_option = this._selectInput.options[this._selectInput.selectedIndex];
        let filetype_filter = selected_option.dataset.filetype_filter;
        //Apply filetype filter to file input
        this._fileInput.setAttribute('accept', filetype_filter);
    }
}