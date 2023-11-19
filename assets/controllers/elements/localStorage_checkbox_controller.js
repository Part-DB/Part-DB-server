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

import {Controller} from "@hotwired/stimulus";

export default class extends Controller
{
    static values = {
        id: String
    }

    connect() {
        this.loadState()
        this.element.addEventListener('change', () => {
            this.saveState()
        });
    }

    loadState() {
        let storageKey = this.getStorageKey();
        let value = localStorage.getItem(storageKey);
        if (value === null) {
            return;
        }

        if (value === 'true') {
            this.element.checked = true
        }
        if (value === 'false') {
            this.element.checked = false
        }
    }

    saveState() {
        let storageKey = this.getStorageKey();

        if (this.element.checked) {
            localStorage.setItem(storageKey, 'true');
        } else {
            localStorage.setItem(storageKey, 'false');
        }
    }

    getStorageKey() {
        if (this.hasIdValue) {
            return 'persistent_checkbox_' + this.idValue
        }

        return 'persistent_checkbox_' + this.element.id;
    }
}
