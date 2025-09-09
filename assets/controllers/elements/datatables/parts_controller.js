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
import TomSelect from "tom-select";

import * as bootbox from "bootbox";

/**
 * This is the datatables controller for parts lists
 */
export default class extends DatatablesController {

    static targets = ['dt', 'selectPanel', 'selectIDs', 'selectCount', 'selectTargetPicker'];

    _confirmed = false;

    isSelectable() {
        //Parts controller is always selectable
        return true;
    }

    _onSelectionChange(e, dt, items) {
        const selected_elements = dt.rows({selected: true});
        const count = selected_elements.count();

        const selectPanel = this.selectPanelTarget;

        //Enable action button based on selection
        if (count > 0) {
            selectPanel.classList.remove('d-none');
            selectPanel.classList.add('sticky-select-bar');
        } else {
            selectPanel.classList.add('d-none');
            selectPanel.classList.remove('sticky-select-bar');
        }

        //Update selection count text
        this.selectCountTarget.innerText = count;

        //Fill selection ID input
        let selected_ids_string = selected_elements.data().map(function(value, index) {
            return value['id']; }
        ).join(",");

        this.selectIDsTarget.value = selected_ids_string;
    }

    updateOptions(select_element, json)
    {
        //Clear options
        select_element.innerHTML = null;
        //$(select_element).selectpicker('destroy');

        //Retrieve the select controller instance
        const select_controller = this.application.getControllerForElementAndIdentifier(select_element, 'elements--structural-entity-select');
        /** @var {TomSelect} tom_select */
        const tom_select = select_controller.getTomSelect();

        tom_select.clear();
        tom_select.clearOptions();

        tom_select.addOptions(json, false);

        //Select first element if there is one (so category select is not empty)
        if(json.length > 0) {
            tom_select.setValue(json[0].value);
        }

        select_element.nextElementSibling.classList.remove('d-none');

        //$(select_element).selectpicker('show');

    }

    updateTargetPicker(event) {
        const element = event.target;

        //Extract the url from the selected option
        const selected_option = element.options[element.options.selectedIndex];
        const url = selected_option.dataset.url;

        const select_target = this.selectTargetPickerTarget;

        if (url) {
            fetch(url)
                .then(response => {
                    response.json().then(json => {
                        this.updateOptions(select_target, json);
                    });
                });
        } else {
            //Hide the select element (the tomselect button is the sibling of the select element)
            select_target.nextElementSibling.classList.add('d-none');
        }

        //If the selected option has a data-turbo attribute, set it to the form
        if (selected_option.dataset.turbo) {
            this.element.dataset.turbo = selected_option.dataset.turbo;
        } else {
            this.element.dataset.turbo = true;
        }
    }

    confirmDeletionAtSubmit(event) {
        //Only show the dialog when selected action is delete
        if (event.target.elements["action"].value !== "delete") {
            return;
        }

        //If a user has not already confirmed the deletion, just let turbo do its work
        if(this._confirmed) {
            this._confirmed = false;
            return;
        }

        //Prevent turbo from doing its work
        event.preventDefault();

        const message = this.element.dataset.deleteMessage;
        const title = this.element.dataset.deleteTitle;

        const form = this.element;
        const that = this;

        //Create a clone of the event with the same submitter, so we can redispatch it if needed
        //We need to do this that way, as we need the submitter info, just calling form.submit() would not work
        this._our_event = new SubmitEvent('submit', {
            submitter: event.submitter,
            bubbles: true, //This line is important, otherwise Turbo will not receive the event
        });

        const confirm = bootbox.confirm({
            message: message, title: title, callback: function (result) {
                //If the dialog was confirmed, then submit the form.
                if (result) {
                    that._confirmed = true;
                    form.dispatchEvent(that._our_event);
                } else {
                    that._confirmed = false;
                }
            }
        });
    }
}

