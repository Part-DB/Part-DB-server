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
import {Popover} from "bootstrap";

export default class extends Controller {
    connect() {
        const thumbnail_url = this.element.dataset.thumbnail;

        this._popover = Popover.getOrCreateInstance(this.element, {
            html: true,
            trigger: 'hover',
            placement: 'right',
            container: 'body',
            'title': this.element.dataset.title ?? '',
            content: function () {
                return '<img class="img-fluid" src="' + thumbnail_url + '" />';
            }
        });

        this._popover.hide();
    }
}