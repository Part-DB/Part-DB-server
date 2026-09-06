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
const IDENTIFIER = "pages--label-lines-mode";

/**
 * This controller is used on the label profile edit/generator forms, to disable the CKEditor WYSIWYG
 * editor on the "lines" textarea whenever the "process_mode" is switched to Twig.
 *
 * The rich text editor treats its content as HTML, so it HTML-escapes characters like <, > and &
 * (e.g. "v => v.id > 1") as soon as its data is synchronized, which corrupts Twig expressions.
 * Therefore, we do not run the WYSIWYG editor at all while Twig mode is selected, and just use a
 * plain textarea instead, so the Twig source is never round-tripped through an HTML parser/serializer.
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

    /** Attached to the "lines" textarea: (de)activates the CKEditor controller based on the mode */
    toggle(event) {
        this.apply(event.detail.mode);
    }

    apply(mode) {
        const controllers = (this.element.dataset.controller ?? '').split(/\s+/).filter(Boolean);
        const hasEditor = controllers.includes(CKEDITOR_CONTROLLER);
        const shouldHaveEditor = mode !== 'twig';

        if (shouldHaveEditor && !hasEditor) {
            controllers.push(CKEDITOR_CONTROLLER);
        } else if (!shouldHaveEditor && hasEditor) {
            controllers.splice(controllers.indexOf(CKEDITOR_CONTROLLER), 1);
        } else {
            return;
        }

        this.element.dataset.controller = controllers.join(' ');
    }
}
