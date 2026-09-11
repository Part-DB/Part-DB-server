/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

const EVENT_NAME = "label-lines-mode:change";
const CKEDITOR_CONTROLLER = "elements--ckeditor";
const CODE_EDITOR_CONTROLLER = "elements--code-editor";
const IDENTIFIER = "pages--label-lines-mode";

/**
 * This controller is used on the label profile edit/generator forms, to swap the "lines" field's editor
 * whenever the "process_mode" is switched between HTML/placeholder and Twig.
 *
 * The CKEditor WYSIWYG editor treats its content as HTML, so it HTML-escapes characters like <, > and &
 * (e.g. "v => v.id > 1") as soon as its data is synchronized, which corrupts Twig expressions. Therefore
 * we do not run it while Twig mode is selected, and use the plain-text code_editor controller (with Twig
 * syntax highlighting) instead, so the Twig source is never round-tripped through an HTML parser/serializer.
 */
export default class extends Controller {
    connect() {
        //Only relevant for the instance attached to the "lines" textarea (identified by data-ck-class,
        //which is only set on that field). Browsers restore the checked state of radio buttons on a
        //plain page reload without firing a "change" event, which would otherwise leave the editor
        //attached/detached inconsistently with whatever mode is actually shown as selected. So we
        //re-sync it to whatever is currently checked as soon as we connect.
        if (this.element.dataset.ckClass === undefined) {
            return;
        }

        const checked = document.querySelector(`[data-controller~="${IDENTIFIER}"] input:checked`);
        if (checked) {
            this.apply(checked.value);
        }
    }

    /** Attached to the "process_mode" radio group: forwards the selected mode to the document */
    notify(event) {
        document.dispatchEvent(new CustomEvent(EVENT_NAME, {
            detail: {mode: event.target.value},
        }));
    }

    /** Attached to the "lines" textarea: swaps the editor controller based on the mode */
    toggle(event) {
        this.apply(event.detail.mode);
    }

    apply(mode) {
        const wanted = mode === 'twig' ? CODE_EDITOR_CONTROLLER : CKEDITOR_CONTROLLER;
        const unwanted = mode === 'twig' ? CKEDITOR_CONTROLLER : CODE_EDITOR_CONTROLLER;

        const controllers = (this.element.dataset.controller ?? '').split(/\s+/).filter(Boolean);
        let changed = false;

        if (!controllers.includes(wanted)) {
            controllers.push(wanted);
            changed = true;
        }

        const unwantedIndex = controllers.indexOf(unwanted);
        if (unwantedIndex !== -1) {
            controllers.splice(unwantedIndex, 1);
            changed = true;
        }

        if (changed) {
            this.element.dataset.controller = controllers.join(' ');
        }
    }
}
