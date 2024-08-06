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
import "tom-select/dist/css/tom-select.bootstrap5.css";
import '../../css/components/tom-select_extensions.css';
import TomSelect from "tom-select";

export default class extends Controller {

    _tomSelect;

    _emptyMessage;

    connect() {
        this._init();
    }

    _init() {
        this._emptyMessage = this.element.getAttribute("data-empty-message") ?? "";
        if (this._emptyMessage === "" && this.element.hasAttribute('title')) {
            this._emptyMessage = this.element.getAttribute('title');
        }


        let settings = {
            plugins: [],
            allowEmptyOption: true,
            selectOnTab: true,
            maxOptions: null,

            render: {
                item: this.renderItem.bind(this),
                option: this.renderOption.bind(this),
            }
        };

        //Load the drag_drop plugin if the select is ordered
        if (this.element.dataset.orderedValue) {
            settings.plugins.push('drag_drop');
        }

        this._tomSelect = new TomSelect(this.element, settings);

        //If the select is ordered, we need to update the value field (with the decoded value from the orderedValue field)
        if (this.element.dataset.orderedValue) {
            const data = JSON.parse(this.element.dataset.orderedValue);
            this._tomSelect.setValue(data);
        }
    }

    getTomSelect() {
        return this._tomSelect;
    }

    renderItem(data, escape) {
        //The empty option is rendered muted
        if (data.value === "") {
            let text = data.text;
            //If no text was defined on the option, we use the empty message
            if (!text) {
                text = this._emptyMessage;
            }
            //And if that is not defined, we use a space to make the option visible
            if (!text) {
                text = " ";
            }
            return '<div class="text-muted">' + escape(text) + '</div>';

        }

        return '<div>' + escape(data.text) + '</div>';
    }

    renderOption(data, escape) {
        //The empty option is rendered muted
        if (data.value === "" && data.text === "") {
            return '<div>&nbsp;</div>';
        }

        return '<div>' + escape(data.text) + '</div>';
    }

    disconnect() {
        super.disconnect();
        //Destroy the TomSelect instance
        this._tomSelect.destroy();
    }
}