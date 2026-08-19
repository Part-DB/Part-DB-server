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

export function isWebNfcAvailable() {
    return window.isSecureContext && "NDEFReader" in window;
}

export function decodeNdefMessage(message) {
    for (const record of message.records) {
        if (!["url", "absolute-url", "text"].includes(record.recordType) || !record.data) continue;

        try {
            const value = new TextDecoder(record.encoding || "utf-8").decode(record.data).trim();
            if (value) return value;
        } catch (_) {
            // Ignore records with unsupported encodings and try the next record.
        }
    }

    return null;
}

export function setScanInputAndSubmit(value) {
    const input = document.getElementById("scan_dialog_input");
    const form = document.getElementById("scan_dialog_form");
    if (!input || !form) return false;

    input.value = value;
    input.dispatchEvent(new Event("input", {bubbles: true}));
    form.requestSubmit();
    return true;
}
