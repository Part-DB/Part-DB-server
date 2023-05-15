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

import JSONFormatter from 'json-formatter-js';

/**
 * This controller implements an element that renders a JSON object as a collapsible tree.
 * The JSON object is passed as a data attribute.
 * You have to apply the controller to a div element or similar block element which can contain other elements.
 */
export default class extends Controller {
    connect() {
        const depth_to_open = this.element.dataset.depthToOpen ?? 0;
        const json_string = this.element.dataset.json;
        const json_object = JSON.parse(json_string);

        const formatter = new JSONFormatter(json_object, depth_to_open);

        this.element.appendChild(formatter.render());
    }
}