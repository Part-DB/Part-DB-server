/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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
import {NiimbotBluetoothClient, ImageEncoder, LabelType} from "@mmote/niimbluelib";
import * as pdfjsLib from "pdfjs-dist";

// Let webpack emit the pdf.js worker as a hashed asset and resolve its URL for us.
pdfjsLib.GlobalWorkerOptions.workerSrc = new URL(
    "pdfjs-dist/build/pdf.worker.min.mjs",
    import.meta.url
).toString();

/**
 * Prints the label PDF (rendered server side, shown in #pdf_preview) directly to a
 * Niimbot thermal printer (e.g. B1) using the Web Bluetooth API via the niimbluelib library.
 *
 * The PDF is rasterized page by page with pdf.js at the printer's native resolution,
 * converted to a 1-bit black/white bitmap and sent to the printer.
 *
 * Requires a secure context (HTTPS or localhost) and a Chromium based browser.
 */
export default class extends Controller {
    static targets = ["button", "status", "density", "labelType", "rotation", "copies", "threshold"];

    static values = {
        // The default printer resolution used only as a fallback until the real model is known.
        dpi: {type: Number, default: 203},
        // Translated, user facing strings passed in from Twig (avoids depending on the JS translation catalog).
        messages: Object,
    };

    connect() {
        // Web Bluetooth is only available in a secure context on Chromium based browsers.
        if (typeof navigator === "undefined" || !navigator.bluetooth) {
            this._setStatus(this._msg("no_bluetooth"), "danger");
            if (this.hasButtonTarget) {
                this.buttonTarget.disabled = true;
            }
        }
    }

    async print(event) {
        event.preventDefault();

        if (typeof navigator === "undefined" || !navigator.bluetooth) {
            this._setStatus(this._msg("no_bluetooth"), "danger");
            return;
        }

        // Read the source PDF (data URI) the same way the download button does.
        const preview = document.getElementById("pdf_preview");
        if (!preview || !preview.data) {
            this._setStatus(this._msg("no_label"), "danger");
            return;
        }

        // Read all settings synchronously *before* the first await, so that the
        // requestDevice() call inside client.connect() still runs within the user gesture.
        const copies = this._intFromTarget("copies", 1, 1);
        const rotation = ((this._intFromTarget("rotation", 0, 0) % 360) + 360) % 360;
        const threshold = this._intFromTarget("threshold", 128, 128);
        const labelType = this._intFromTarget("labelType", LabelType.WithGaps, LabelType.WithGaps);
        const requestedDensity = this._intFromTarget("density", 0, 0);
        const pdfBytes = this._dataUriToBytes(preview.data);

        this._busy(true);

        const client = new NiimbotBluetoothClient();
        let connected = false;
        let printTask = null;

        try {
            this._setStatus(this._msg("connecting"));
            await client.connect();
            connected = true;

            const meta = client.getModelMetadata();
            const dpi = (meta && meta.dpi) ? meta.dpi : this.dpiValue;
            const printhead = meta ? meta.printheadPixels : null;
            const modelName = meta ? meta.model : (client.getPrinterInfo().modelId ?? "?");

            // Density: use requested value, otherwise the model default, clamped to the model range.
            let density = requestedDensity > 0 ? requestedDensity : (meta ? meta.densityDefault : 3);
            if (meta) {
                density = Math.min(Math.max(density, meta.densityMin), meta.densityMax);
            }

            this._setStatus(this._msg("connected", {"%model%": modelName}));

            const pdf = await pdfjsLib.getDocument({data: pdfBytes}).promise;
            const numPages = pdf.numPages;

            const taskName = client.getPrintTaskType() ?? "B1";
            printTask = client.abstraction.newPrintTask(taskName, {
                totalPages: numPages * copies,
                labelType: labelType,
                density: density,
                statusPollIntervalMs: 150,
                statusTimeoutMs: 8000,
            });

            await printTask.printInit();

            for (let i = 1; i <= numPages; i++) {
                this._setStatus(this._msg("rendering", {"%page%": i, "%total%": numPages}));

                const page = await pdf.getPage(i);
                let canvas = await this._renderPage(page, dpi);
                canvas = this._rotate(canvas, rotation);
                this._threshold(canvas, threshold);

                const encoded = ImageEncoder.encodeCanvas(canvas, "top");

                if (printhead && encoded.cols > printhead) {
                    this._setStatus(
                        this._msg("too_wide", {"%width%": encoded.cols, "%max%": printhead}),
                        "warning"
                    );
                }

                this._setStatus(this._msg("printing", {"%page%": i, "%total%": numPages}));
                await printTask.printPage(encoded, copies);
                await printTask.waitForPageFinished();
            }

            await printTask.waitForFinished();
            this._setStatus(this._msg("done", {"%count%": numPages * copies}), "success");
        } catch (e) {
            console.error(e);
            const message = (e && e.message) ? e.message : String(e);
            this._setStatus(this._msg("error", {"%message%": message}), "danger");
        } finally {
            if (connected) {
                try {
                    await client.abstraction.printEnd();
                } catch (e) {
                    // Ignore cleanup errors, they would mask the original error.
                }
                try {
                    await client.disconnect();
                } catch (e) {
                    // Ignore.
                }
            }
            this._busy(false);
        }
    }

    /**
     * Renders a single PDF page to a canvas at the given resolution (dpi).
     * The canvas is filled white first, because thermal printers only burn the non-white pixels.
     */
    async _renderPage(page, dpi) {
        const viewport = page.getViewport({scale: dpi / 72});
        const canvas = document.createElement("canvas");
        canvas.width = Math.max(1, Math.round(viewport.width));
        canvas.height = Math.max(1, Math.round(viewport.height));
        const ctx = canvas.getContext("2d");
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        await page.render({canvasContext: ctx, viewport}).promise;
        return canvas;
    }

    /**
     * Rotates a canvas by 0/90/180/270 degrees clockwise and returns a new canvas.
     * Done before thresholding so the resulting bitmap stays crisp.
     */
    _rotate(src, deg) {
        if (deg % 360 === 0) {
            return src;
        }
        const swap = deg === 90 || deg === 270;
        const dst = document.createElement("canvas");
        dst.width = swap ? src.height : src.width;
        dst.height = swap ? src.width : src.height;
        const ctx = dst.getContext("2d");
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, dst.width, dst.height);
        ctx.translate(dst.width / 2, dst.height / 2);
        ctx.rotate(deg * Math.PI / 180);
        ctx.drawImage(src, -src.width / 2, -src.height / 2);
        return dst;
    }

    /**
     * Converts a canvas to pure black/white in place using a luminance threshold.
     */
    _threshold(canvas, threshold) {
        const ctx = canvas.getContext("2d");
        const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const d = img.data;
        for (let i = 0; i < d.length; i += 4) {
            const luminance = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
            const value = luminance < threshold ? 0 : 255;
            d[i] = value;
            d[i + 1] = value;
            d[i + 2] = value;
            d[i + 3] = 255;
        }
        ctx.putImageData(img, 0, 0);
    }

    _dataUriToBytes(dataUri) {
        const base64 = dataUri.substring(dataUri.indexOf(",") + 1);
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes;
    }

    _intFromTarget(name, fallback, emptyValue) {
        const targetName = "has" + name.charAt(0).toUpperCase() + name.slice(1) + "Target";
        if (!this[targetName]) {
            return fallback;
        }
        const raw = this[name + "Target"].value;
        if (raw === "" || raw === null || raw === undefined) {
            return emptyValue;
        }
        const parsed = parseInt(raw, 10);
        return Number.isNaN(parsed) ? fallback : parsed;
    }

    _busy(busy) {
        if (this.hasButtonTarget) {
            this.buttonTarget.disabled = busy;
        }
        for (const name of ["density", "labelType", "rotation", "copies", "threshold"]) {
            const targetName = name + "Target";
            const hasName = "has" + name.charAt(0).toUpperCase() + name.slice(1) + "Target";
            if (this[hasName]) {
                this[targetName].disabled = busy;
            }
        }
    }

    _msg(key, replacements = {}) {
        let text = (this.messagesValue && this.messagesValue[key]) ? this.messagesValue[key] : key;
        for (const [search, value] of Object.entries(replacements)) {
            text = text.replace(search, value);
        }
        return text;
    }

    _setStatus(text, type = "muted") {
        if (!this.hasStatusTarget) {
            return;
        }
        this.statusTarget.textContent = text;
        this.statusTarget.className = "small text-" + type;
    }
}
