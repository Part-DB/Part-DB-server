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

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['items'];
    static values = {
        prototype: String,
        prototypeName: { type: String, default: '__name__' },
        index: { type: Number, default: 0 },
    };

    connect() {
        if (!this.hasIndexValue || Number.isNaN(this.indexValue)) {
            this.indexValue = this.itemsTarget?.children.length || 0;
        }
    }

    add(event) {
        event.preventDefault();

        const encodedProto = this.prototypeValue || '';
        const placeholder = this.prototypeNameValue || '__name__';
        if (!encodedProto || !this.itemsTarget) return;

        const protoHtml = this._decodeHtmlAttribute(encodedProto);

        const idx = this.indexValue;
        const html = protoHtml.replace(new RegExp(placeholder, 'g'), String(idx));

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const newItem = wrapper.firstElementChild;
        if (newItem) {
            this.itemsTarget.appendChild(newItem);
            this.indexValue = idx + 1;
        }
    }

    remove(event) {
        event.preventDefault();
        const row = event.currentTarget.closest('.tc-item');
        if (row) row.remove();
    }

    _decodeHtmlAttribute(str) {
        const tmp = document.createElement('textarea');
        tmp.innerHTML = str;
        return tmp.value || tmp.textContent || '';
    }
}
