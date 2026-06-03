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

/**
 * TomSelect plugin for dirty-check integration and form reset support.
 *
 * Sets data-default-value on the underlying <select> so the dirty-check
 * controller can detect changes, and restores that value when the form is reset.
 */
export default function form_reset_handler() {
    const self = this;
    const input = this.input;

    // Multiple selects not yet supported
    if (input.multiple) {
        return;
    }

    // Always capture the initial value, even empty string.
    // Empty string is falsy, so the old `|| null` guard would silently skip it,
    // leaving data-default-value unset and breaking the dirty check for blank defaults.
    input.dataset.defaultValue = input.value;

    if (input.form) {
        input.form.addEventListener('reset', () => {
            input.value = input.dataset.defaultValue ?? '';
            self.sync();
        });
    }
}
