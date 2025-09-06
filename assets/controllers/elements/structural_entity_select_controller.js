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

import "tom-select/dist/css/tom-select.bootstrap5.css";
import '../../css/components/tom-select_extensions.css';
import TomSelect from "tom-select";
import {Controller} from "@hotwired/stimulus";

import {trans, ENTITY_SELECT_GROUP_NEW_NOT_ADDED_TO_DB} from '../../translator.js'

import TomSelect_autoselect_typed from '../../tomselect/autoselect_typed/autoselect_typed'
TomSelect.define('autoselect_typed', TomSelect_autoselect_typed)

export default class extends Controller {
    _tomSelect;

    _emptyMessage;

    connect() {

        //Extract empty message from data attribute
        this._emptyMessage = this.element.getAttribute("data-empty-message") ?? "";

        const allowAdd = this.element.getAttribute("data-allow-add") === "true";
        const addHint = this.element.getAttribute("data-add-hint") ?? "";




        let settings = {
            allowEmptyOption: true,
            selectOnTab: true,
            maxOptions: null,
            create: allowAdd ? this.createItem.bind(this) : false,
            createFilter: this.createFilter.bind(this),

            // This three options allow us to paste element names with commas: (see issue #538)
            maxItems: 1,
            delimiter: "$$VERY_LONG_DELIMITER_THAT_SHOULD_NEVER_APPEAR$$",
            splitOn: null,
            dropdownParent: 'body',

            searchField: [
                {field: "text", weight : 2},
                {field: "parent", weight : 0.5},
                {field: "path", weight : 1.0},
            ],

            render: {
                item: this.renderItem.bind(this),
                option: this.renderOption.bind(this),
                option_create: (data, escape)  => {
                    //If the input starts with "->", we prepend the current selected value, for easier extension of existing values
                    //This here handles the display part, while the createItem function handles the actual creation
                    if (data.input.startsWith("->")) {
                        //Get current selected value
                        const current = this._tomSelect.getItem(this._tomSelect.getValue()).textContent.replaceAll("→", "->").trim();
                        //Prepend it to the input
                        if (current) {
                            data.input = current + " " + data.input;
                        } else {
                            //If there is no current value, we remove the "->"
                            data.input = data.input.substring(2);
                        }
                    }

                    return '<div class="create"><i class="fa-solid fa-plus fa-fw"></i>&nbsp;<strong>' + escape(data.input) + '</strong>&hellip;&nbsp;' +
                        '<small class="text-muted float-end">(' + addHint +')</small>' +
                        '</div>';
                },
            },

            //Add callbacks to update validity
            onInitialize: this.updateValidity.bind(this),
            onChange: this.updateValidity.bind(this),

            plugins: {
                "autoselect_typed": {},
            }
        };

        //Add clear button plugin, if an empty option is present
        if (this.element.querySelector("option[value='']") !== null) {
            settings.plugins["clear_button"] = {};
        }

        this._tomSelect = new TomSelect(this.element, settings);
        //Do not do a sync here as this breaks the initial rendering of the empty option
        //this._tomSelect.sync();
    }

    createItem(input, callback) {

        //If the input starts with "->", we prepend the current selected value, for easier extension of existing values
        if (input.startsWith("->")) {
            //Get current selected value
            let current = this._tomSelect.getItem(this._tomSelect.getValue()).textContent.replaceAll("→", "->").trim();
            //Replace no break spaces with normal spaces
            current = current.replaceAll("\u00A0", " ");
            //Prepend it to the input
            if (current) {
                input = current + " " + input;
            } else {
                //If there is no current value, we remove the "->"
                input = input.substring(2);
            }
        }

        callback({
            //$%$ is a special value prefix, that is used to identify items, that are not yet in the DB
            value: '$%$' + input,
            text: input,
            not_in_db_yet: true,
        });
    }

    createFilter(input) {

        //Normalize the input (replace spacing around arrows)
        if (input.includes("->")) {
            const inputs = input.split("->");
            inputs.forEach((value, index) => {
                inputs[index] = value.trim();
            });
            input = inputs.join("->");
        } else {
            input = input.trim();
        }

        const options = this._tomSelect.options;
        //Iterate over all options and check if the input is already present
        for (let index in options) {
            const option = options[index];
            if (option.path === input) {
                return false;
            }
        }

        return true;
    }


    updateValidity() {
        //Mark this input as invalid, if the selected option is disabled

        const input = this.element;
        const selectedOption = input.options[input.selectedIndex];

        if (selectedOption && selectedOption.disabled) {
            input.setCustomValidity("This option was disabled. Please select another option.");
        } else {
            input.setCustomValidity("");
        }
    }

    getTomSelect() {
        return this._tomSelect;
    }

    renderItem(data, escape) {
        //Render empty option as full row
        if (data.value === "") {
            if (this._emptyMessage) {
                return '<div class="tom-select-empty-option"><span class="text-muted"><b>' + escape(this._emptyMessage) + '</b></span></div>';
            } else {
                return '<div>&nbsp;</div>';
            }
        }

        if (data.short) {
            let short = escape(data.short)

            //Make text italic, if the item is not yet in the DB
            if (data.not_in_db_yet) {
                short = '<i>' + short + '</i>';
            }

            return '<div><b>' + short + '</b></div>';
        }

        let name = "";
        if (data.parent) {
            name += escape(data.parent) + "&nbsp;→&nbsp;";
        }

        if (data.not_in_db_yet) {
            //Not yet added items are shown italic and with a badge
            name += "<i><b>" + escape(data.text) + "</b></i>" + "<span class='ms-3 badge bg-info badge-info'>" + trans(ENTITY_SELECT_GROUP_NEW_NOT_ADDED_TO_DB) + "</span>";
        } else {
            name += "<b>" + escape(data.text) + "</b>";
        }

        return '<div>' + (data.image ? "<img class='structural-entity-select-image' style='margin-right: 5px;' ' src='" + data.image + "'/>" : "") + name + '</div>';
    }

    renderOption(data, escape) {
        //Render empty option as full row
        if (data.value === "") {
            if (this._emptyMessage) {
                return '<div class="tom-select-empty-option"><span class="text-muted">' + escape(this._emptyMessage) + '</span></div>';
            } else {
                return '<div>&nbsp;</div>';
            }
        }


        //Indent the option according to the level
        let level_html = '&nbsp;&nbsp;&nbsp;'.repeat(data.level);

        let filter_badge = "";
        if (data.filetype_filter) {
            filter_badge = '<span class="badge bg-warning float-end"><i class="fa-solid fa-file-circle-exclamation"></i>&nbsp;' + escape(data.filetype_filter) + '</span>';
        }

        let symbol_badge = "";
        if (data.symbol) {
            symbol_badge = '<span class="badge bg-primary ms-2">' + escape(data.symbol) + '</span>';
        }

        let parent_badge = "";
        if (data.parent) {
            parent_badge = '<span class="ms-3 badge rounded-pill bg-secondary float-end picker-us"><i class="fa-solid fa-folder-tree"></i>&nbsp;' + escape(data.parent) + '</span>';
        }

        let image = "";
        if (data.image) {
            image = '<img class="structural-entity-select-image" style="margin-left: 5px;" src="' + data.image + '"/>';
        }

        return '<div>' + level_html + escape(data.text) + image + symbol_badge + parent_badge + filter_badge + '</div>';
    }

    disconnect() {
        super.disconnect();
        //Destroy the TomSelect instance
        this._tomSelect.destroy();
    }

}
