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
    _onInfoChange = null;
    _onFormSubmit = null;

    connect() {
        // Prevent double init if connect fires twice
        if (this._scanner) return;

        // clear last decoded barcode when state changes on info box
        const info = document.getElementById("scan_dialog_info_mode");
        if (info) {
            this._onInfoChange = () => {
                this._lastDecodedText = "";
            };
            info.addEventListener("change", this._onInfoChange);
        }

        // Stop camera cleanly before manual form submit (prevents broken camera after reload)
        const form = document.getElementById("scan_dialog_form");
        if (form) {
            this._onFormSubmit = () => {
                try {
                    const p = this._scanner?.clear?.();
                    if (p && typeof p.then === "function") p.catch(() => {});
                } catch (_) {
                    // ignore
                }
            };

            // capture=true so we run before other handlers / navigation
            form.addEventListener("submit", this._onFormSubmit, { capture: true });
        }

        const isMobile = window.matchMedia("(max-width: 768px)").matches;

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
            // Key change: shrink preview height on mobile
            ...(isMobile ? { aspectRatio: 1.0 } : {}),
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

        // Unbind info-mode change handler (always do this, even if scanner is null)
        const info = document.getElementById("scan_dialog_info_mode");
        if (info && this._onInfoChange) {
            info.removeEventListener("change", this._onInfoChange);
        }
        this._onInfoChange = null;

        // remove the onForm submit handler
        const form = document.getElementById("scan_dialog_form");
        if (form && this._onFormSubmit) {
            form.removeEventListener("submit", this._onFormSubmit, { capture: true });
        }
        this._onFormSubmit = null;

        if (!scanner) return;

        try {
            const p = scanner.clear?.();
            if (p && typeof p.then === "function") p.catch(() => {});
        } catch (_) {
            // ignore
        }
    }


    async onScanSuccess(decodedText) {
        if (!decodedText) return;

        const normalized = String(decodedText).trim();
        if (!normalized) return;

        // scan once per barcode
        if (normalized === this._lastDecodedText) return;

        // If a request/submit is in-flight, ignore scans.
        if (this._submitting) return;

        // Mark as handled immediately (prevents spam even if callback fires repeatedly)
        this._lastDecodedText = normalized;
        this._submitting = true;

        // Clear previous augmented result immediately to avoid stale info
        // lingering when the next scan is not augmented (or is transient/junk).
        const el = document.getElementById("scan-augmented-result");
        if (el) el.innerHTML = "";

        //Put our decoded Text into the input box
        const input = document.getElementById("scan_dialog_input");
        if (input) input.value = decodedText;

        const infoMode = !!document.getElementById("scan_dialog_info_mode")?.checked;

        try {
            const data = await this.lookup(normalized, infoMode);

            // ok:false = transient junk decode; ignore without wiping UI
            if (!data || data.ok !== true) {
                this._lastDecodedText = ""; // allow retry
                return;
            }

            // If info mode is OFF and part was found -> redirect
            if (!infoMode && data.found && data.redirectUrl) {
                window.location.assign(data.redirectUrl);
                return;
            }

            // If info mode is OFF and part was NOT found, redirect to create part URL
            if (!infoMode && !data.found && data.createUrl) {
                window.location.assign(data.createUrl);
                return;
            }

            // Otherwise render returned fragment HTML
            if (typeof data.html === "string" && data.html !== "") {
                const el = document.getElementById("scan-augmented-result");
                if (el) el.innerHTML = data.html;
            }
        } catch (e) {
            console.warn("[barcode_scan] lookup failed", e);
            // allow retry on failure
            this._lastDecodedText = "";
        } finally {
            this._submitting = false;
        }
    }


    async lookup(decodedText, infoMode) {
        const form = document.getElementById("scan_dialog_form");
        if (!form) return { ok: false };

        generateCsrfToken(form);

        const mode =
            document.querySelector('input[name="scan_dialog[mode]"]:checked')?.value ?? "";

        const body = new URLSearchParams();
        body.set("input", decodedText);
        if (mode !== "") body.set("mode", mode);
        body.set("info_mode", infoMode ? "1" : "0");

        const headers = {
            "Accept": "application/json",
            "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
            ...generateCsrfHeaders(form),
        };

        const url = this.element.dataset.lookupUrl;
        if (!url) throw new Error("Missing data-lookup-url on #reader-box");

        const resp = await fetch(url, {
            method: "POST",
            headers,
            body: body.toString(),
            credentials: "same-origin",
        });

        if (!resp.ok) {
            throw new Error(`lookup failed: HTTP ${resp.status}`);
        }

        return await resp.json();
    }
}
