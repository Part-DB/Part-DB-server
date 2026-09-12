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
import {trans} from "../../translator";
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

    static targets = ["name", "symbol", "unit", "valueText", "definition", "newChoiceValue"]

    _tomSelect;
    _valueTomSelect;
    _initialized = false;
    _resetting = false;
    _initialState;
    _form;
    _resetHandler;
    _resetTimer;

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
        if (this._resetting || !this._initialized || !this.hasDefinitionTarget || !this.hasValueTextTarget) {
            return;
        }

        const definitionId = item.dataset.definitionId;
        if (definitionId === undefined || !/^\d+$/.test(definitionId) || Number(definitionId) < 1) {
            this.setDefinition(null);
            this.applyInputDefinition('text', []);
            this.setLinkedFieldState(false);

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
        this.setLinkedFieldState(true);
    }

    onItemRemove() {
        if (this._resetting || !this._initialized || !this.hasDefinitionTarget || !this.hasValueTextTarget) {
            return;
        }

        this.setDefinition(null);
        this.applyInputDefinition('text', []);
        this.setLinkedFieldState(false);
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

    applyInputDefinition(inputType, choices, restoredValue = undefined, deprecatedChoices = []) {
        this.destroyValueTomSelect();
        const oldElement = this.valueTextTarget;
        const currentValue = restoredValue ?? oldElement.value;
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

            for (const choice of deprecatedChoices) {
                const option = new Option(
                    trans('parameter_definition.choice.deprecated_label', {'%choice%': choice}),
                    choice,
                );
                option.dataset.deprecatedChoice = 'true';
                newElement.add(option);
            }

            newElement.value = [...choices, ...deprecatedChoices].includes(currentValue) ? currentValue : '';
        } else {
            newElement.type = 'text';
            newElement.classList.remove('form-select', 'form-select-sm');
            newElement.classList.add('form-control', 'form-control-sm');
            newElement.value = currentValue;
        }

        oldElement.replaceWith(newElement);
        this.clearPendingChoice();
        if (useChoice) {
            this.setupValueTomSelect(newElement);
        }
        newElement.dispatchEvent(new Event('change', {bubbles: true}));
    }

    setLinkedFieldState(linked) {
        if (this.hasSymbolTarget) {
            this.symbolTarget.readOnly = linked;
        }
        if (this.hasUnitTarget) {
            this.unitTarget.readOnly = linked;
        }
    }

    normalizeChoice(value) {
        return value.trim().toLocaleLowerCase();
    }

    findCanonicalChoice(value) {
        const normalized = this.normalizeChoice(value);
        if (normalized === '' || !this._valueTomSelect) {
            return null;
        }

        for (const option of Object.values(this._valueTomSelect.options)) {
            if (!option.pending_choice && this.normalizeChoice(String(option.value ?? '')) === normalized) {
                return String(option.value);
            }
        }

        return null;
    }

    clearPendingChoice() {
        if (this.hasNewChoiceValueTarget) {
            this.newChoiceValueTarget.value = '';
        }
    }

    setupValueTomSelect(element) {
        const canAddChoice = element.dataset.canAddChoice === 'true';
        const pendingChoice = this.hasNewChoiceValueTarget ? this.newChoiceValueTarget.value.trim() : '';
        const options = Array.from(element.options).map(option => ({
            value: option.value,
            text: option.text,
            pending_choice: pendingChoice !== '' && option.value === pendingChoice,
        }));

        this._valueTomSelect = new TomSelect(element, {
            plugins: {
                'clear_button': {},
                'form_reset_handler': {},
            },
            options,
            items: element.value === '' ? [] : [element.value],
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            maxItems: 1,
            allowEmptyOption: true,
            placeholder: trans('parameter.choice.nothing_selected'),
            createOnBlur: false,
            selectOnTab: true,
            createFilter: input => canAddChoice
                && this.normalizeChoice(input) !== ''
                && this.findCanonicalChoice(input) === null,
            create: canAddChoice ? (input, callback) => {
                const choice = input.trim();
                if (choice === '' || this.findCanonicalChoice(choice) !== null) {
                    callback(false);
                    return;
                }

                callback({value: choice, text: choice, pending_choice: true});
            } : false,
            onType: input => {
                const canonical = this.findCanonicalChoice(input);
                if (canonical !== null && canonical !== input) {
                    this._valueTomSelect.setTextboxValue(canonical);
                    this._valueTomSelect.refreshOptions(false);
                }
            },
            onItemAdd: value => {
                // Initial items are added while the TomSelect constructor is still running, before the instance has
                // been assigned to _valueTomSelect.
                const option = this._valueTomSelect?.options[value]
                    ?? options.find(candidate => candidate.value === value);
                if (this.hasNewChoiceValueTarget) {
                    this.newChoiceValueTarget.value = option?.pending_choice ? String(option.value) : '';
                }
            },
            onItemRemove: () => this.clearPendingChoice(),
            render: {
                option_create: (data, escape) => '<div class="create">'
                    + escape(trans('parameter.choice.add_new', {'%value%': data.input}))
                    + ' <span class="badge bg-info">' + escape(trans('parameter.choice.new')) + '</span></div>',
                item: (data, escape) => '<div>' + escape(data.text)
                    + (data.pending_choice
                        ? ' <span class="badge bg-info">' + escape(trans('parameter.choice.new')) + '</span>'
                        : '')
                    + '</div>',
            },
        });
    }

    destroyValueTomSelect() {
        this._valueTomSelect?.destroy();
        this._valueTomSelect = undefined;
    }

    captureInitialState() {
        const valueElement = this.valueTextTarget;
        const isChoice = valueElement.tagName === 'SELECT';
        const definitionId = this.hasDefinitionTarget ? this.definitionTarget.value : '';
        const selectedDefinition = this.hasDefinitionTarget
            ? this.definitionTarget.options[this.definitionTarget.selectedIndex]
            : null;

        this._initialState = {
            name: this.nameTarget.value,
            symbol: this.hasSymbolTarget ? this.symbolTarget.value : '',
            unit: this.hasUnitTarget ? this.unitTarget.value : '',
            definitionId,
            definitionName: selectedDefinition?.text ?? this.nameTarget.value,
            inputType: isChoice ? 'choice' : 'text',
            choices: isChoice
                ? Array.from(valueElement.options)
                    .filter(option => option.value !== '' && option.dataset.deprecatedChoice !== 'true')
                    .map(option => option.value)
                : [],
            deprecatedChoices: isChoice
                ? Array.from(valueElement.options)
                    .filter(option => option.value !== '' && option.dataset.deprecatedChoice === 'true')
                    .map(option => option.value)
                : [],
            value: valueElement.value,
        };
    }

    onFormReset() {
        this._resetting = true;
        clearTimeout(this._resetTimer);

        // The browser restores native form controls after the reset event. Rebuild the composite TomSelect state on
        // the next task, once both the native reset and the individual TomSelect reset handlers have completed.
        this._resetTimer = setTimeout(() => this.restoreInitialState(), 0);
    }

    restoreInitialState() {
        if (!this._initialState || !this.element.isConnected) {
            this._resetting = false;
            return;
        }

        const state = this._initialState;
        this.clearPendingChoice();

        if (state.name === '') {
            this._tomSelect.clear(true);
        } else {
            if (!this._tomSelect.options[state.name]) {
                this._tomSelect.addOption({name: state.name});
            }
            this._tomSelect.setValue(state.name, true);
        }

        if (this.hasSymbolTarget) {
            this.symbolTarget.value = state.symbol;
            this.symbolTarget.dispatchEvent(new Event('input'));
        }
        if (this.hasUnitTarget) {
            this.unitTarget.value = state.unit;
            this.unitTarget.dispatchEvent(new Event('input'));
        }

        if (this.hasDefinitionTarget) {
            this.setDefinition(
                state.definitionId === '' ? null : state.definitionId,
                state.definitionName
            );
        }
        this.applyInputDefinition(
            state.inputType,
            state.choices,
            state.value,
            state.deprecatedChoices,
        );
        this.setLinkedFieldState(state.definitionId !== '');
        this._resetting = false;
    }

    connect() {
        this.captureInitialState();
        this._form = this.nameTarget.form;
        if (this._form) {
            this._resetHandler = this.onFormReset.bind(this);
            // Capture phase ensures callbacks emitted by the TomSelect reset plugins see _resetting=true.
            this._form.addEventListener('reset', this._resetHandler, true);
        }

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
        this.setLinkedFieldState(this.hasDefinitionTarget && this.definitionTarget.value !== '');
        if (this.hasValueTextTarget && this.valueTextTarget.tagName === 'SELECT') {
            this.setupValueTomSelect(this.valueTextTarget);
        }
    }

    disconnect() {
        super.disconnect();
        clearTimeout(this._resetTimer);
        if (this._form && this._resetHandler) {
            this._form.removeEventListener('reset', this._resetHandler, true);
        }
        //Destroy the TomSelect instance
        this._tomSelect?.destroy();
        this.destroyValueTomSelect();
    }
}
