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
        this._lastDecodedText = "";

        // Unbind info-mode change handler (always do this, even if scanner is null)
        const info = document.getElementById("scan_dialog_info_mode");
        if (info && this._onInfoChange) {
            info.removeEventListener("change", this._onInfoChange);
        }
        this._onInfoChange = null;

        if (!scanner) return;

        try {
            const p = scanner.clear?.();
            if (p && typeof p.then === "function") p.catch(() => {});
        } catch (_) {
            // ignore
        }
    }


    onScanSuccess(decodedText) {
        if (!decodedText) return;

        const normalized = String(decodedText).trim();
        if (!normalized) return;

        // scan once per barcode
        if (normalized === this._lastDecodedText) return;

        // Mark as handled immediately (prevents spam even if callback fires repeatedly)
        this._lastDecodedText = normalized;

        document.getElementById('scan_dialog_input').value = decodedText;
        //Submit form
        document.getElementById('scan_dialog_form').requestSubmit();
    }
}
