/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-server).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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
import {AlertSwal} from "../../helpers/swal";
import {trans} from "../../translator.js";

/**
 * Drives the hidden component image generator to render a preview for every candidate row, then attaches
 * the checked ones to their parts via the per-part generate-image endpoint (with a progress bar).
 */
export default class extends Controller {
    static targets = ["row", "progress", "progressBar", "attachBtn", "edaBtn",
        "batchPitch", "batchDiameter", "batchColor", "batchVoltage", "batchShape", "batchLead", "batchTolerance",
        "batchPower", "batchPpm", "batchPackage"];

    connect() {
        this.tryRenderPreviews(0);
    }

    /** The component image generator controller instance (retries briefly, since it may connect after us). */
    calcController() {
        const el = this.element.querySelector('[data-controller*="omponent-image-generator"]');
        if (!el) {
            return null;
        }
        const c = this.application.getControllerForElementAndIdentifier(el, "pages--component-image-generator");
        if (c && typeof c.generateSvg === "function") {
            return c;
        }
        return null;
    }

    tryRenderPreviews(attempt) {
        const calc = this.calcController();
        if (!calc) {
            if (attempt < 20) {
                setTimeout(() => this.tryRenderPreviews(attempt + 1), 50);
            }
            return;
        }
        this.rowTargets.forEach((row) => this.renderPreview(row, calc));
    }

    renderPreview(row, calc) {
        //Each row carries its own appearance inputs; shape/lead length are batch-wide.
        const colorEl = row.querySelector("[data-row-color]");
        const diamEl = row.querySelector("[data-row-diameter]");
        const pitchEl = row.querySelector("[data-row-pitch]");
        const voltEl = row.querySelector("[data-row-voltage]");
        const tolEl = row.querySelector("[data-row-tolerance]");
        const powerEl = row.querySelector("[data-row-power]");
        const ppmEl = row.querySelector("[data-row-ppm]");
        const pkgEl = row.querySelector("[data-row-package]");
        const svg = calc.generateSvg(row.dataset.type, parseFloat(row.dataset.value), {
            voltage: voltEl && voltEl.value ? parseFloat(voltEl.value) : (row.dataset.voltage ? parseFloat(row.dataset.voltage) : 0),
            package: pkgEl && pkgEl.value ? pkgEl.value : (row.dataset.package || null),
            power: powerEl && powerEl.value ? parseFloat(powerEl.value) : (row.dataset.power ? parseFloat(row.dataset.power) : 0),
            ppm: ppmEl && ppmEl.value ? parseFloat(ppmEl.value) : (row.dataset.ppm ? parseFloat(row.dataset.ppm) : 0),
            tolerance: tolEl
                ? (tolEl.value ? parseFloat(tolEl.value) : null)
                : (row.dataset.tolerance ? parseFloat(row.dataset.tolerance) : null),
            pitch: pitchEl ? pitchEl.value : (row.dataset.pitch || null),
            diameter: diamEl && diamEl.value ? parseFloat(diamEl.value) : (row.dataset.diameter ? parseFloat(row.dataset.diameter) : 0),
            subtype: row.dataset.subtype || null,
            marking: row.dataset.marking || null,
            bodyColor: colorEl ? colorEl.value : null,
            shape: this.hasBatchShapeTarget ? this.batchShapeTarget.value : "disc",
            leadLength: this.hasBatchLeadTarget ? this.batchLeadTarget.value : "medium",
        });
        row.dataset.svg = svg;
        const cell = row.querySelector("[data-bulk-preview]");
        if (cell) {
            cell.innerHTML = svg || "";
        }
        //Clear the calculator's scratch SVG so its leftover copy can't collide (duplicate ids)
        //with the copy we just placed in this row's cell — that made the last preview render off.
        if (typeof calc.clearScratchSvg === "function") {
            calc.clearScratchSvg();
        }
    }

    /** Re-render every preview (used by batch controls and the shape/lead selects). */
    regenerate() {
        const calc = this.calcController();
        if (calc) {
            this.rowTargets.forEach((row) => this.renderPreview(row, calc));
        }
    }

    /** Re-render only the row whose per-row appearance input changed. */
    regenerateRow(event) {
        const calc = this.calcController();
        const row = event.target.closest("tr");
        if (calc && row) {
            this.renderPreview(row, calc);
        }
    }

    /** Copy a batch-bar value into every row's matching input, then re-render. */
    applyToAll(selector, value) {
        if (value === "" || value === null || value === undefined) {
            return;
        }
        this.rowTargets.forEach((row) => {
            const el = row.querySelector(selector);
            if (el) {
                el.value = value;
            }
        });
        this.regenerate();
    }

    applyColor() {
        this.applyToAll("[data-row-color]", this.hasBatchColorTarget ? this.batchColorTarget.value : "");
    }

    applyDiameter() {
        this.applyToAll("[data-row-diameter]", this.hasBatchDiameterTarget ? this.batchDiameterTarget.value : "");
    }

    applyPitch() {
        this.applyToAll("[data-row-pitch]", this.hasBatchPitchTarget ? this.batchPitchTarget.value : "");
    }

    applyVoltage() {
        this.applyToAll("[data-row-voltage]", this.hasBatchVoltageTarget ? this.batchVoltageTarget.value : "");
    }

    applyPower() {
        this.applyToAll("[data-row-power]", this.hasBatchPowerTarget ? this.batchPowerTarget.value : "");
    }

    applyPpm() {
        this.applyToAll("[data-row-ppm]", this.hasBatchPpmTarget ? this.batchPpmTarget.value : "");
    }

    applyPackage() {
        this.applyToAll("[data-row-package]", this.hasBatchPackageTarget ? this.batchPackageTarget.value : "");
    }

    /**
     * Applies the batch tolerance to every row that offers that option (tolerance choices differ
     * between capacitors and resistors). "—" always applies, so it can also clear every row.
     */
    applyTolerance() {
        if (!this.hasBatchToleranceTarget) {
            return;
        }
        const value = this.batchToleranceTarget.value;
        this.rowTargets.forEach((row) => {
            const el = row.querySelector("[data-row-tolerance]");
            if (el && (value === "" || Array.from(el.options).some((o) => o.value === value))) {
                el.value = value;
            }
        });
        this.regenerate();
    }

    /** A body-colour quick-pick button: set the batch colour input, then apply it to all rows. */
    pickColor(event) {
        if (this.hasBatchColorTarget) {
            this.batchColorTarget.value = event.currentTarget.dataset.color;
        }
        this.applyColor();
    }

    /** Returns to the previous page (the parts list the action came from); the link's href is the no-JS fallback. */
    goBack(event) {
        if (window.history.length > 1) {
            event.preventDefault();
            window.history.back();
        }
    }

    /** Header checkbox: check/uncheck every row. */
    toggleAll(event) {
        const checked = event.currentTarget.checked;
        this.rowTargets.forEach((row) => {
            const cb = row.querySelector("input[type=checkbox]");
            if (cb) {
                cb.checked = checked;
            }
        });
    }

    /** Attaches the generated picture of each checked row to its part. */
    attachSelected() {
        return this.runBatch(
            (row) => {
                if (!(row.dataset.svg || "").includes("<svg")) {
                    return null;
                }
                const body = new FormData();
                body.append("svg", row.dataset.svg);
                body.append("name", row.dataset.name || "Generated image");
                body.append("preview", "1");
                //Replace the existing generated image for parts that already had a picture.
                if (row.dataset.overwrite) {
                    body.append("overwrite", "1");
                }
                body.append("_token", row.dataset.csrf || "");
                return {url: row.dataset.endpoint, body};
            },
            trans("tools.bulk_gen.attached"),
            this.hasAttachBtnTarget ? this.attachBtnTarget : null
        );
    }

    /** Writes the (editable) KiCad symbol / footprint / reference of each checked row to its part. */
    writeEda() {
        return this.runBatch(
            (row) => {
                //Prefer the values the user may have edited in the row's inputs; fall back to the suggestions.
                const symInput = row.querySelector("[data-bulk-eda-symbol]");
                const refInput = row.querySelector("[data-bulk-eda-reference]");
                const fpInput = row.querySelector("[data-bulk-eda-footprint]");
                const body = new FormData();
                body.append("kicad_symbol", symInput ? symInput.value.trim() : (row.dataset.kicadSymbol || ""));
                body.append("reference_prefix", refInput ? refInput.value.trim() : (row.dataset.referencePrefix || ""));
                body.append("kicad_footprint", fpInput ? fpInput.value.trim() : (row.dataset.kicadFootprint || ""));
                body.append("_token", row.dataset.edaCsrf || "");
                return {url: row.dataset.edaEndpoint, body};
            },
            trans("tools.bulk_gen.eda_written"),
            this.hasEdaBtnTarget ? this.edaBtnTarget : null
        );
    }

    /**
     * Runs a POST for every checked row, updating the progress bar. buildRequest(row) returns
     * {url, body} or null to skip the row.
     */
    async runBatch(buildRequest, doneWord, btn) {
        const rows = this.rowTargets.filter((row) => {
            const cb = row.querySelector("input[type=checkbox]");
            return cb && cb.checked;
        });
        if (rows.length === 0) {
            AlertSwal.fire({title: trans("tools.value_calc.attach.nothing")});
            return;
        }

        if (btn) {
            btn.disabled = true;
        }
        if (this.hasProgressTarget) {
            this.progressTarget.classList.remove("d-none");
        }

        let done = 0;
        let ok = 0;
        let failed = 0;
        for (const row of rows) {
            const req = buildRequest(row);
            if (!req) {
                done++;
                this.updateProgress(done, rows.length);
                continue;
            }
            try {
                const resp = await fetch(req.url, {method: "POST", body: req.body, headers: {"X-Requested-With": "XMLHttpRequest"}});
                const data = await resp.json().catch(() => ({}));
                if (resp.ok && data && data.success) {
                    ok++;
                    row.classList.add("table-success");
                } else {
                    failed++;
                    row.classList.add("table-danger");
                }
            } catch (e) {
                failed++;
                row.classList.add("table-danger");
            }
            done++;
            this.updateProgress(done, rows.length);
        }

        if (btn) {
            btn.disabled = false;
        }
        AlertSwal.fire({
            title: `${ok} / ${rows.length} ${doneWord}${failed ? ` · ${failed} ${trans("tools.bulk_gen.failed")}` : ""}`,
            icon: failed ? "warning" : "success",
        });
    }

    updateProgress(done, total) {
        const pct = total > 0 ? Math.round((done / total) * 100) : 0;
        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = pct + "%";
            this.progressBarTarget.textContent = `${done}/${total}`;
        }
    }
}
