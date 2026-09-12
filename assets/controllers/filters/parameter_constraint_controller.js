/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

import {Controller} from '@hotwired/stimulus';
import TomSelect from 'tom-select';
import {trans} from '../../translator';
import 'tom-select/dist/css/tom-select.bootstrap5.css';
import '../../css/components/tom-select_extensions.css';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        url: String,
        initialInputType: String,
        initialChoices: Array,
        initialDeprecatedChoices: Array,
        deprecatedChoiceLabel: String,
    };

    static targets = [
        'name',
        'definition',
        'symbol',
        'symbolContainer',
        'unit',
        'unitContainer',
        'numericContainer',
        'valueOperator',
        'valueText',
    ];

    connect() {
        this._initialized = false;
        this._restoring = false;
        this._initialState = this.captureState();
        this._form = this.nameTarget.form;
        this._resetHandler = this.onFormReset.bind(this);
        this._form?.addEventListener('reset', this._resetHandler);

        this.setupNameTomSelect();

        const linked = this.definitionTarget.value !== '';
        this.setAdHocFieldState(linked, linked);
        this.configureCurrentValueEditor(linked ? this.initialInputTypeValue : 'text');
    }

    disconnect() {
        clearTimeout(this._resetTimer);
        this._form?.removeEventListener('reset', this._resetHandler);
        this._nameTomSelect?.destroy();
        this.destroyValueTomSelect();
    }

    setupNameTomSelect() {
        const settings = {
            plugins: ['clear_button', 'restore_on_backspace'],
            persistent: false,
            maxItems: 1,
            delimiter: 'VERY_L0NG_D€LIMITER_WHICH_WILL_NEVER_BE_ENCOUNTERED_IN_A_STRING',
            createOnBlur: true,
            selectOnTab: true,
            create: true,
            searchField: 'name',
            valueField: 'name',
            labelField: 'name',
            clearAfterSelect: true,
            onItemAdd: value => this.onNameItemAdd(value),
            onItemRemove: () => this.onNameItemRemove(),
            onInitialize: () => {
                this._initialized = true;
            },
            render: {
                option: (data, escape) => {
                    let details = '';
                    if (data.symbol) {
                        details += escape(data.symbol);
                    }
                    if (data.unit) {
                        details += (details === '' ? '' : ' ') + '[' + escape(data.unit) + ']';
                    }

                    return '<div><span>' + escape(data.name) + '</span>'
                        + (details === '' ? '' : '<br><small class="text-muted">' + details + '</small>')
                        + '</div>';
                },
                item: (data, escape) => '<div>' + escape(data.name) + '</div>',
            },
        };

        if (this.hasUrlValue) {
            const baseUrl = this.urlValue;
            settings.load = (query, callback) => {
                fetch(baseUrl.replace('__QUERY__', encodeURIComponent(query)))
                    .then(response => response.json())
                    .then(data => callback(data))
                    .catch(() => callback());
            };
        }

        this._nameTomSelect = new TomSelect(this.nameTarget, settings);
    }

    onNameItemAdd(value) {
        if (!this._initialized || this._restoring) {
            return;
        }

        const suggestion = this._nameTomSelect.options[value] ?? {};
        const definitionId = Number(suggestion.definition_id);
        if (Number.isInteger(definitionId) && definitionId > 0) {
            this.enterDefinitionMode({
                id: definitionId,
                name: suggestion.name ?? value,
                inputType: suggestion.input_type === 'choice' ? 'choice' : 'text',
                choices: Array.isArray(suggestion.choices) ? suggestion.choices : [],
                deprecatedChoices: Array.isArray(suggestion.deprecated_choices)
                    ? suggestion.deprecated_choices
                    : [],
            });
            return;
        }

        this.enterAdHocMode(suggestion);
    }

    onNameItemRemove() {
        if (!this._initialized || this._restoring) {
            return;
        }

        this.enterAdHocMode();
    }

    enterDefinitionMode(definition) {
        this.setDefinition(definition.id, definition.name);
        this.setAdHocFieldState(true, true);
        this.replaceValueEditor(
            definition.inputType,
            definition.choices,
            definition.deprecatedChoices,
            '',
            '',
        );
    }

    enterAdHocMode(suggestion = {}) {
        this.setDefinition(null);
        this.setAdHocFieldState(false, true);
        this.replaceValueEditor('text', [], [], '', '');

        this.symbolTarget.value = suggestion.symbol ?? '';
        this.unitTarget.value = suggestion.unit ?? '';
        this.symbolTarget.dispatchEvent(new Event('input', {bubbles: true}));
        this.unitTarget.dispatchEvent(new Event('input', {bubbles: true}));
    }

    setDefinition(id, name = '') {
        if (id === null) {
            this.definitionTarget.value = '';
        } else {
            const value = String(id);
            if (!Array.from(this.definitionTarget.options).some(option => option.value === value)) {
                this.definitionTarget.add(new Option(name, value));
            }
            this.definitionTarget.value = value;
        }

        this.definitionTarget.dispatchEvent(new Event('change', {bubbles: true}));
    }

    setAdHocFieldState(linked, clear) {
        for (const container of [this.symbolContainerTarget, this.numericContainerTarget, this.unitContainerTarget]) {
            container.classList.toggle('d-none', linked);
        }

        const fields = [
            this.symbolTarget,
            ...this.numericContainerTarget.querySelectorAll('input, select'),
            this.unitTarget,
        ];

        for (const field of fields) {
            if (clear) {
                field.value = '';
                if (field.tomselect) {
                    field.tomselect.clear(true);
                    field.tomselect.sync();
                }
            }

            field.disabled = linked;
            if (field.tomselect) {
                if (linked) {
                    field.tomselect.disable();
                } else {
                    field.tomselect.enable();
                }
            }
        }

        if (clear) {
            this.symbolTarget.dispatchEvent(new Event('input', {bubbles: true}));
            this.unitTarget.dispatchEvent(new Event('input', {bubbles: true}));
        }
    }

    configureCurrentValueEditor(inputType) {
        const choiceMode = inputType === 'choice' && this.valueTextTarget.tagName === 'SELECT';
        this.valueOperatorTarget.classList.toggle('d-none', choiceMode);
        this.valueOperatorTarget.value = choiceMode ? '=' : this.valueOperatorTarget.value;

        if (choiceMode) {
            this.setupValueTomSelect(this.valueTextTarget);
        }
    }

    replaceValueEditor(inputType, choices, deprecatedChoices, value, operator) {
        this.destroyValueTomSelect();
        const oldElement = this.valueTextTarget;
        const choiceMode = inputType === 'choice';
        const newElement = document.createElement(choiceMode ? 'select' : 'input');

        for (const attribute of oldElement.attributes) {
            if (attribute.name !== 'type' && attribute.name !== 'class') {
                newElement.setAttribute(attribute.name, attribute.value);
            }
        }
        newElement.setAttribute('data-controller', '');

        if (choiceMode) {
            newElement.className = 'form-select';
            newElement.add(new Option('', ''));
            for (const choice of choices) {
                newElement.add(new Option(choice, choice));
            }
            for (const choice of deprecatedChoices) {
                newElement.add(new Option(
                    this.deprecatedChoiceLabelValue.replace('__PARAMETER_CHOICE__', () => choice),
                    choice,
                ));
            }
            newElement.value = [...choices, ...deprecatedChoices].includes(value) ? value : '';
        } else {
            newElement.type = 'search';
            newElement.className = 'form-control';
            newElement.value = value;
        }

        oldElement.replaceWith(newElement);
        this.valueOperatorTarget.classList.toggle('d-none', choiceMode);
        this.valueOperatorTarget.value = choiceMode ? '=' : operator;

        if (choiceMode) {
            this.setupValueTomSelect(newElement);
        }
    }

    setupValueTomSelect(element) {
        if (element.tomselect) {
            element.tomselect.destroy();
        }

        this._valueTomSelect = new TomSelect(element, {
            plugins: ['clear_button'],
            allowEmptyOption: true,
            create: false,
            maxItems: 1,
            selectOnTab: true,
            placeholder: trans('parameter.choice.nothing_selected'),
        });
    }

    destroyValueTomSelect() {
        this._valueTomSelect?.destroy();
        this._valueTomSelect = undefined;
    }

    captureState() {
        return {
            name: this.nameTarget.value,
            definitionId: this.definitionTarget.value,
            definitionName: this.definitionTarget.selectedOptions[0]?.text ?? this.nameTarget.value,
            inputType: this.initialInputTypeValue,
            choices: [...this.initialChoicesValue],
            deprecatedChoices: [...this.initialDeprecatedChoicesValue],
            symbol: this.symbolTarget.value,
            unit: this.unitTarget.value,
            numeric: Array.from(this.numericContainerTarget.querySelectorAll('input, select')).map(field => field.value),
            value: this.valueTextTarget.value,
            operator: this.valueOperatorTarget.value,
        };
    }

    onFormReset() {
        clearTimeout(this._resetTimer);
        this._resetTimer = setTimeout(() => this.restoreInitialState(), 0);
    }

    restoreInitialState() {
        if (!this.element.isConnected) {
            return;
        }

        const state = this._initialState;
        this._restoring = true;
        this._nameTomSelect.clear(true);
        if (state.name !== '') {
            if (!this._nameTomSelect.options[state.name]) {
                this._nameTomSelect.addOption({name: state.name});
            }
            this._nameTomSelect.setValue(state.name, true);
        }
        this.setDefinition(state.definitionId === '' ? null : state.definitionId, state.definitionName);

        this.symbolTarget.value = state.symbol;
        this.unitTarget.value = state.unit;
        const numericFields = this.numericContainerTarget.querySelectorAll('input, select');
        numericFields.forEach((field, index) => {
            field.value = state.numeric[index] ?? '';
            field.tomselect?.sync();
        });

        this.replaceValueEditor(
            state.inputType,
            state.choices,
            state.deprecatedChoices,
            state.value,
            state.operator,
        );
        const linked = state.definitionId !== '';
        this.setAdHocFieldState(linked, linked);
        this._restoring = false;
    }
}
