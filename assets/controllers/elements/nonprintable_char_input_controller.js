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

export default class extends Controller {

    _hiddenInput;

    connect() {
        this.element.addEventListener("input", this._onInput.bind(this));

        // We use a hidden input to store the actual value of the field, which is submitted with the form.
        // The visible input is just for user interaction and can contain non-printable characters, which are not allowed in the hidden input.
        this._hiddenInput = document.createElement("input");
        this._hiddenInput.type = "hidden";
        this._hiddenInput.name = this.element.name;
        this.element.removeAttribute("name");
        this.element.parentNode.insertBefore(this._hiddenInput, this.element.nextSibling);
    }

    _onInput(event) {
        // Remove non-printable characters from the input value and store them in the hidden input

        const normalizedValue = this.decodeNonPrintableChars(this.element.value);
        this._hiddenInput.value = normalizedValue;

        // Encode non-printable characters in the visible input to their Unicode Control picture representation
        const encodedValue = this.encodeNonPrintableChars(normalizedValue);
        if (encodedValue !== this.element.value) {
            this.element.value = encodedValue;
        }
    }

    /**
     * Encodes non-printable characters in the given string via their Unicode Control picture representation, e.g. \n becomes ␊ and \t becomes ␉.
     * This allows us to display non-printable characters in the input field without breaking the form submission.
     * @param str
     */
    encodeNonPrintableChars(str) {
        return str.replace(/[\x00-\x1F\x7F]/g, (char) => {
            const code = char.charCodeAt(0);
            return String.fromCharCode(0x2400 + code);
        });
    }

    /**
     * Decodes the Unicode Control picture representation of non-printable characters back to their original form, e.g. ␊ becomes \n and ␉ becomes \t.
     * @param str
     */
    decodeNonPrintableChars(str) {
        return str.replace(/[\u2400-\u241F\u2421]/g, (char) => {
            const code = char.charCodeAt(0) - 0x2400;
            return String.fromCharCode(code);
        });
    }
}
