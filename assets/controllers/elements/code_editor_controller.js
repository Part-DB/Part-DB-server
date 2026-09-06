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
import {CodeJar} from "codejar";
import {withLineNumbers} from "codejar-linenumbers";
import hljs from "highlight.js/lib/core";
import xml from "highlight.js/lib/languages/xml";
import twig from "highlight.js/lib/languages/twig";

import "codejar-linenumbers/es/codejar-linenumbers.css";
import "../../css/components/code_editor.css";

//The "twig" grammar embeds "xml" for the surrounding HTML, so both need to be registered
hljs.registerLanguage('xml', xml);
hljs.registerLanguage('twig', twig);

/**
 * A lightweight code editor with Twig/HTML syntax highlighting and line numbers (via CodeJar +
 * codejar-linenumbers + highlight.js), used as a replacement for the CKEditor WYSIWYG editor on
 * textareas containing Twig template source.
 *
 * highlight.js's "twig" grammar (which embeds its "xml" grammar for the surrounding HTML) actually
 * recognizes real Twig tag/filter/function names, unlike a generic delimiter-only grammar.
 *
 * Unlike CKEditor, this never parses the content into an HTML document/DOM tree that gets
 * reserialized - it is a plain contenteditable text buffer with a highlighting overlay - so it
 * cannot corrupt Twig syntax (e.g. "filter(v => v.id > 1)") by HTML-escaping it.
 */

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        //Apply the default value of the source element as data attribute, so that dirty-form-controller can detect changes
        this.element.dataset.defaultValue = this.element.defaultValue;

        this.editorElement = document.createElement('div');
        this.editorElement.className = 'code-editor form-control';
        this.editorElement.setAttribute('spellcheck', 'false');
        this.editorElement.setAttribute('translate', 'no');

        if (this.element.disabled) {
            this.editorElement.setAttribute('contenteditable', 'false');
        }

        //Try to keep roughly the same height as the textarea it replaces
        const rows = this.element.getAttribute('rows');
        if (rows) {
            this.editorElement.style.minHeight = `calc(${parseInt(rows, 10)} * 1.5em + .75rem)`;
        }

        this.element.insertAdjacentElement('afterend', this.editorElement);
        this.element.hidden = true;

        const highlight = (editorElement) => {
            editorElement.innerHTML = hljs.highlight(editorElement.textContent, {language: 'twig'}).value;
        };

        this.jar = CodeJar(this.editorElement, withLineNumbers(highlight), {
            tab: '    ',
            //Twig/HTML is full of "{{", "{%" and filter parentheses. CodeJar's default smart-editing
            //features are meant for brace-indented code (C-like languages) and misinterpret these:
            //auto-closing would turn every "{" the user types into a stray "{}" pair, and the
            //"push closing bracket to its own line" logic would silently insert extra blank lines
            //whenever Enter is pressed near one of them. Both are disabled here to keep the editor
            //a predictable, literal text buffer.
            addClosing: false,
            indentOn: /(?!)/,
            moveToNewLine: /(?!)/,
        });

        //withLineNumbers() lazily wraps editorElement in its own positioning container on first highlight
        this.jar.updateCode(this.element.value);
        this.wrapElement = this.editorElement.parentElement;

        this.jar.onUpdate((code) => {
            this.element.value = code;
            //Dispatch the input event for further treatment (e.g. dirty-form-controller)
            this.element.dispatchEvent(new Event("input", {bubbles: true}));
        });

        if (this.element.form) {
            this.resetListener = () => {
                if (this.element.dataset.defaultValue !== undefined) {
                    this.element.value = this.element.dataset.defaultValue;
                    this.jar.updateCode(this.element.dataset.defaultValue);
                }
            };
            this.element.form.addEventListener("reset", this.resetListener);
        }
    }

    disconnect() {
        if (this.jar) {
            this.jar.destroy();
            this.jar = null;
        }

        if (this.element.form && this.resetListener) {
            this.element.form.removeEventListener("reset", this.resetListener);
            this.resetListener = null;
        }

        if (this.wrapElement) {
            this.wrapElement.remove();
            this.wrapElement = null;
            this.editorElement = null;
        }

        this.element.hidden = false;
    }
}
