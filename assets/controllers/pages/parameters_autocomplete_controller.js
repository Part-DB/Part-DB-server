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
import katex from "katex";
import "katex/dist/katex.css";


import TomSelect_click_to_edit from '../../tomselect/click_to_edit/click_to_edit'
import TomSelect_autoselect_typed from '../../tomselect/autoselect_typed/autoselect_typed'
import TomSelect_form_reset_handler from '../../tomselect/form_reset_handler/form_reset_handler'

TomSelect.define('click_to_edit', TomSelect_click_to_edit)
TomSelect.define('autoselect_typed', TomSelect_autoselect_typed)
TomSelect.define('form_reset_handler', TomSelect_form_reset_handler)

/* stimulusFetch: 'lazy' */
export default class extends Controller
{
    static values = {
        url: String,
    }

    static targets = ["name", "symbol", "unit", "valueText", "definition"]

    _tomSelect;
    _initialized = false;

    onItemAdd(value, item) {
        //Retrieve the unit and symbol from the item
        const symbol = item.dataset.symbol;
        const unit = item.dataset.unit;

        if (this.symbolTarget && symbol !== undefined) {
            this.symbolTarget.value = symbol;
            //Trigger input event to update the preview
            this.symbolTarget.dispatchEvent(new Event('input'));
        }
        if (this.unitTarget && unit !== undefined) {
            this.unitTarget.value = unit;
            //Trigger input event to update the preview
            this.unitTarget.dispatchEvent(new Event('input'));
        }

        // TomSelect emits onItemAdd for the value already present while initializing an existing row. The server has
        // rendered that row from its persisted definition, so only an explicit user selection may change the link.
        if (!this._initialized || !this.hasDefinitionTarget || !this.hasValueTextTarget) {
            return;
        }

        const definitionId = item.dataset.definitionId;
        if (definitionId === undefined || !/^\d+$/.test(definitionId) || Number(definitionId) < 1) {
            this.setDefinition(null);
            this.applyInputDefinition('text', []);

            return;
        }

        let choices = [];
        if (item.dataset.choices) {
            try {
                choices = JSON.parse(item.dataset.choices);
            } catch (_) {
                choices = [];
            }
        }

        this.setDefinition(definitionId, item.dataset.definitionName ?? value);
        this.applyInputDefinition(item.dataset.inputType ?? 'text', choices);
    }

    onItemRemove() {
        if (!this._initialized || !this.hasDefinitionTarget || !this.hasValueTextTarget) {
            return;
        }

        this.setDefinition(null);
        this.applyInputDefinition('text', []);
    }

    setDefinition(definitionId, name = '') {
        this.definitionTarget.replaceChildren();

        const emptyOption = new Option('', '');
        this.definitionTarget.add(emptyOption);

        if (definitionId !== null) {
            const option = new Option(name, definitionId, true, true);
            this.definitionTarget.add(option);
            this.definitionTarget.value = definitionId;
        } else {
            this.definitionTarget.value = '';
        }

        this.definitionTarget.dispatchEvent(new Event('change', {bubbles: true}));
    }

    applyInputDefinition(inputType, choices) {
        const oldElement = this.valueTextTarget;
        const currentValue = oldElement.value;
        const useChoice = inputType === 'choice' && Array.isArray(choices);
        const newElement = document.createElement(useChoice ? 'select' : 'input');

        for (const attribute of oldElement.attributes) {
            if (attribute.name !== 'type') {
                newElement.setAttribute(attribute.name, attribute.value);
            }
        }

        if (useChoice) {
            newElement.classList.remove('form-control', 'form-control-sm');
            newElement.classList.add('form-select', 'form-select-sm');
            newElement.add(new Option('', ''));

            for (const choice of choices) {
                newElement.add(new Option(choice, choice));
            }

            newElement.value = choices.includes(currentValue) ? currentValue : '';
        } else {
            newElement.type = 'text';
            newElement.classList.remove('form-select', 'form-select-sm');
            newElement.classList.add('form-control', 'form-control-sm');
            newElement.value = currentValue;
        }

        oldElement.replaceWith(newElement);
        newElement.dispatchEvent(new Event('change', {bubbles: true}));
    }

    connect() {
        const settings = {
            plugins: {
                'autoselect_typed': {},
                'click_to_edit': {},
                'clear_button': {},
                'restore_on_backspace': {},
                'form_reset_handler': {}
            },
            persistent: false,
            maxItems: 1,
            //This a an ugly solution to disable the delimiter parsing of the TomSelect plugin
            delimiter: 'VERY_L0NG_D€LIMITER_WHICH_WILL_NEVER_BE_ENCOUNTERED_IN_A_STRING',
            createOnBlur: true,
            selectOnTab: true,
            create: true,
            searchField: "name",
            //labelField: "name",
            valueField: "name",
            clearAfterSelect: true,
            onItemAdd: this.onItemAdd.bind(this),
            onItemRemove: this.onItemRemove.bind(this),
            onInitialize: () => {
                this._initialized = true;
            },
            render: {
                option: (data, escape) => {
                    let tmp = '<div>'
                        + '<span>' + escape(data.name) + '</span><br>';

                    if (data.symbol) {
                        tmp += '<span>' + katex.renderToString(data.symbol) + '</span>'
                    }
                    if (data.unit) {
                        let unit  = data.unit.replace(/%/g, '\\%');
                        unit = "\\mathrm{" + unit + "}";
                        tmp += '<span class="ms-2">' + katex.renderToString('[' + unit + ']') + '</span>'
                    }


                    //+ '<span class="text-muted">' + escape(data.unit) + '</span>'
                    tmp += '</div>';

                    return tmp;
                },
                item: (data, escape) => {
                    //We use the item to transfert data to the onItemAdd function using data attributes
                    const element = document.createElement('div');
                    element.innerText = data.name;
                    if(data.unit !== undefined) {
                        element.dataset.unit = data.unit;
                    }
                    if (data.symbol !== undefined) {
                        element.dataset.symbol = data.symbol;
                    }
                    if (data.definition_id !== undefined) {
                        element.dataset.definitionId = data.definition_id;
                        element.dataset.definitionName = data.name;
                    }
                    if (data.input_type !== undefined) {
                        element.dataset.inputType = data.input_type;
                    }
                    if (data.choices !== undefined) {
                        element.dataset.choices = JSON.stringify(data.choices);
                    }

                    return element.outerHTML;
                }
            }
        };

        if(this.urlValue) {
            const base_url = this.urlValue;
            settings.load = (query, callback) => {
                const url = base_url.replace('__QUERY__', encodeURIComponent(query));

                fetch(url)
                    .then(response => response.json())
                    .then(json => {
                        //const data = json.map(x => {return {"value": x, "text": x}});
                        callback(json);
                    }).catch(()=>{
                    callback();
                });
            }
        }

        this._tomSelect = new TomSelect(this.nameTarget, settings);
    }

    disconnect() {
        super.disconnect();
        //Destroy the TomSelect instance
        this._tomSelect?.destroy();
    }
}
