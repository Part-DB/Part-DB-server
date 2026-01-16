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
//import * as ZXing from "@zxing/library";

import {Html5QrcodeScanner, Html5Qrcode} from "@part-db/html5-qrcode";
import { generateCsrfToken, generateCsrfHeaders } from "../csrf_protection_controller";

/* stimulusFetch: 'lazy' */

export default class extends Controller {
    _scanner = null;
    _submitting = false;
    _lastDecodedText = "";

    connect() {
        // Prevent double init if connect fires twice
        if (this._scanner) return;

        this.bindModeToggles();

        //This function ensures, that the qrbox is 70% of the total viewport
        let qrboxFunction = function(viewfinderWidth, viewfinderHeight) {
            let minEdgePercentage = 0.7; // 70%
            let minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
            let qrboxSize = Math.floor(minEdgeSize * minEdgePercentage);
            return {
                width: qrboxSize,
                height: qrboxSize
            };
        }

        //Try to get the number of cameras. If the number is 0, then the promise will fail, and we show the warning dialog
        Html5Qrcode.getCameras().catch(() => {
            document.getElementById("scanner-warning")?.classList.remove("d-none");
        });

        this._scanner = new Html5QrcodeScanner(this.element.id, {
            fps: 10,
            qrbox: qrboxFunction,
            experimentalFeatures: {
                //This option improves reading quality on android chrome
                useBarCodeDetectorIfSupported: true,
            },
        }, false);

        this._scanner.render(this.onScanSuccess.bind(this));
    }

    disconnect() {
        // If we already stopped/cleared before submit, nothing to do.
        const scanner = this._scanner;
        this._scanner = null;
        this._submitting = false;
        this._lastDecodedText = "";
        this.unbindModeToggles();

        if (!scanner) return;

        try {
            const p = scanner.clear?.();
            if (p && typeof p.then === "function") p.catch(() => {});
        } catch (_) {
            // ignore
        }
    }

    /**
     * Add events to Mode checkboxes so they both can't be selected at the same time
     */
    bindModeToggles() {
        const info = document.getElementById("scan_dialog_info_mode");
        const aug  = document.getElementById("scan_dialog_augmented_mode");
        if (!info || !aug) return;

        const onInfoChange = () => {
            if (info.checked) aug.checked = false;
        };
        const onAugChange = () => {
            if (aug.checked) info.checked = false;
        };

        info.addEventListener("change", onInfoChange);
        aug.addEventListener("change", onAugChange);

        // Save references so we can remove listeners on disconnect
        this._onInfoChange = onInfoChange;
        this._onAugChange = onAugChange;
    }

    unbindModeToggles() {
        const info = document.getElementById("scan_dialog_info_mode");
        const aug  = document.getElementById("scan_dialog_augmented_mode");
        if (!info || !aug) return;

        if (this._onInfoChange) info.removeEventListener("change", this._onInfoChange);
        if (this._onAugChange) aug.removeEventListener("change", this._onAugChange);

        this._onInfoChange = null;
        this._onAugChange = null;
    }



    async onScanSuccess(decodedText) {
        if (!decodedText) return;

        const normalized = String(decodedText).trim();

        // If we already handled this exact barcode and it's still showing, ignore.
        if (normalized === this._lastDecodedText) return;

        // If a request/submit is in-flight, ignore scans.
        if (this._submitting) return;

        // Mark as handled immediately (prevents spam even if callback fires repeatedly)
        this._lastDecodedText = normalized;
        this._submitting = true;

        //Put our decoded Text into the input box
        const input = document.getElementById("scan_dialog_input");
        if (input) input.value = decodedText;

        const augmented = !!document.getElementById("scan_dialog_augmented_mode")?.checked;

        // If augmented mode: do NOT submit the form.
        if (augmented) {
            try {
                await this.lookupAndRender(decodedText);
            } catch (e) {
                console.warn("[barcode_scan] augmented lookup failed", e);
                // Allow retry on failure by clearing last decoded text
                this._lastDecodedText = "";
            } finally {
                // allow scanning again
                this._submitting = false;
            }
            return;
        }

        // Non-augmented: Stop scanner BEFORE submitting to avoid camera transition races
        try {
            if (this._scanner?.clear) {
                await this._scanner.clear();
            }
        } catch (_) {
            // ignore
        } finally {
            this._scanner = null;
        }

        //Submit form
        document.getElementById("scan_dialog_form")?.requestSubmit();
    }

    async lookupAndRender(decodedText) {
        const form = document.getElementById("scan_dialog_form");
        if (!form) return;

        // Ensure the hidden csrf field has been converted from placeholder -> real token + cookie set
        generateCsrfToken(form);

        const mode =
            document.querySelector('input[name="scan_dialog[mode]"]:checked')?.value ?? "";

        const body = new URLSearchParams();
        body.set("input", decodedText);
        if (mode !== "") body.set("mode", mode);

        const headers = {
            "Accept": "text/html",
            "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
            ...generateCsrfHeaders(form), // adds the special CSRF header Symfony expects (if enabled)
        };

        const resp = await fetch(this.element.dataset.augmentedUrl, {
            method: "POST",
            headers,
            body: body.toString(),
            credentials: "same-origin",
        });

        const html = await resp.text();

        const el = document.getElementById("scan-augmented-result");
        if (el) el.innerHTML = html;
    }
}
