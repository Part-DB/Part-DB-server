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
import {AlertSwal} from "../../helpers/swal";
import {trans} from "../../translator.js";

/**
 * Color definitions for the resistor color code.
 * digit:      significant figure (null if the color can't be used for a digit band)
 * multiplier: factor applied by a multiplier band
 * tolerance:  tolerance in percent (null if not usable as tolerance band)
 * temp:       temperature coefficient in ppm/K (null if not usable as temp. band)
 * hex/text:   colors used to draw the band and a readable label on top of it
 */
const RESISTOR_COLORS = {
    black:  {digit: 0,    multiplier: 1e0,  tolerance: null, temp: 250,  hex: "#000000", text: "#ffffff"},
    brown:  {digit: 1,    multiplier: 1e1,  tolerance: 1,    temp: 100,  hex: "#5c3a21", text: "#ffffff"},
    red:    {digit: 2,    multiplier: 1e2,  tolerance: 2,    temp: 50,   hex: "#c8102e", text: "#ffffff"},
    orange: {digit: 3,    multiplier: 1e3,  tolerance: null, temp: 15,   hex: "#f25c05", text: "#000000"},
    yellow: {digit: 4,    multiplier: 1e4,  tolerance: null, temp: 25,   hex: "#f2c200", text: "#000000"},
    green:  {digit: 5,    multiplier: 1e5,  tolerance: 0.5,  temp: 20,   hex: "#1a8f3c", text: "#ffffff"},
    blue:   {digit: 6,    multiplier: 1e6,  tolerance: 0.25, temp: 10,   hex: "#0a4ea3", text: "#ffffff"},
    violet: {digit: 7,    multiplier: 1e7,  tolerance: 0.1,  temp: 5,    hex: "#6a2c91", text: "#ffffff"},
    grey:   {digit: 8,    multiplier: 1e8,  tolerance: 0.05, temp: 1,    hex: "#808080", text: "#ffffff"},
    white:  {digit: 9,    multiplier: 1e9,  tolerance: null, temp: null, hex: "#f5f5f5", text: "#000000"},
    gold:   {digit: null, multiplier: 0.1,  tolerance: 5,    temp: null, hex: "#c2a000", text: "#000000"},
    silver: {digit: null, multiplier: 0.01, tolerance: 10,   temp: null, hex: "#b3b3b3", text: "#000000"},
};

// Capacitor tolerance letter codes (percent, or absolute in pF for small caps)
const CAP_TOLERANCE = {
    B: "±0.10 pF", C: "±0.25 pF", D: "±0.5 pF", F: "±1%", G: "±2%",
    J: "±5%", K: "±10%", M: "±20%", Z: "+80% / -20%",
};

// EIA-96 significant value lookup (code 01..96)
const EIA96_VALUES = [
    100, 102, 105, 107, 110, 113, 115, 118, 121, 124, 127, 130, 133, 137, 140, 143,
    147, 150, 154, 158, 162, 165, 169, 174, 178, 182, 187, 191, 196, 200, 205, 210,
    215, 221, 226, 232, 237, 243, 249, 255, 261, 267, 274, 280, 287, 294, 301, 309,
    316, 324, 332, 340, 348, 357, 365, 374, 383, 392, 402, 412, 422, 432, 442, 453,
    464, 475, 487, 499, 511, 523, 536, 549, 562, 576, 590, 604, 619, 634, 649, 665,
    681, 698, 715, 732, 750, 768, 787, 806, 825, 845, 866, 887, 909, 931, 953, 976,
];
const EIA96_MULTIPLIERS = {
    Z: 0.001, Y: 0.01, R: 0.01, X: 0.1, S: 0.1, A: 1, B: 10, C: 100, D: 1000, E: 10000, F: 100000,
};

// Typical dimensions of axial THT resistors per power rating.
// len/dia = body length and diameter (mm), pitch = typical lead spacing (mm).
const RESISTOR_POWERS = {
    "0.125": {label: "1/8 W", len: 3.4, dia: 1.9, pitch: 7.62, pitchIn: "0.3\""},
    "0.25": {label: "1/4 W", len: 6.3, dia: 2.4, pitch: 10.16, pitchIn: "0.4\""},
    "0.5": {label: "1/2 W", len: 9.0, dia: 3.2, pitch: 12.7, pitchIn: "0.5\""},
    "1": {label: "1 W", len: 11.5, dia: 4.5, pitch: 15.24, pitchIn: "0.6\""},
    "2": {label: "2 W", len: 15.5, dia: 5.0, pitch: 20.32, pitchIn: "0.8\""},
};

// Standard SMD (chip) packages: imperial code -> metric code, size (mm), power (W).
const SMD_PACKAGES = {
    "0201": {metric: "0603", l: 0.6, w: 0.3, power: 0.05},
    "0402": {metric: "1005", l: 1.0, w: 0.5, power: 0.063},
    "0603": {metric: "1608", l: 1.6, w: 0.8, power: 0.1},
    "0805": {metric: "2012", l: 2.0, w: 1.25, power: 0.125},
    "1206": {metric: "3216", l: 3.2, w: 1.6, power: 0.25},
    "1210": {metric: "3225", l: 3.2, w: 2.5, power: 0.33},
    "2010": {metric: "5025", l: 5.0, w: 2.5, power: 0.5},
    "2512": {metric: "6332", l: 6.3, w: 3.2, power: 1.0},
};

// Common lead pitches for radial ceramic capacitors.
const CAP_PITCHES = {
    "2.5": "0.1\"",
    "2.54": "0.1\"",
    "5": "0.2\"",
    "5.08": "0.2\"",
    "7.5": "",
    "10": "",
    "15": "",
};

// Lead length presets (extra pixels the leads extend below the body).
const CAP_LEAD_LENGTHS = {short: 48, medium: 84, long: 130};

// Axial resistor lead length presets (pixels each lead extends beyond the body; medium = original look).
const RESISTOR_LEAD_LENGTHS = {short: 40, medium: 90, long: 150};

const DIM_COLOR = "#6b7280";

export default class extends Controller {
    static targets = [
        "resistorSvg", "bandSelects", "resistorResult", "resistorValueInput", "resistorBodyColor",
        "resistorPower", "resistorSpec",
        "capValueInput", "capCodeInput", "capTolerance", "capSvg", "capResult", "capBodyColor",
        "capPitch", "capDiameter", "capVoltage", "capSpec", "capShape", "capLead",
        "smdValueInput", "smdCode3", "smdCode4", "smdEia96",
        "smdSvg", "smdResult", "smdBodyColor", "smdPackage", "smdSpec",
        "smdIndValueInput", "smdIndCode", "smdIndPackage", "smdIndBodyColor", "smdIndSvg", "smdIndSpec",
        "smdCapValueInput", "smdCapPackage", "smdCapBodyColor", "smdCapVoltage", "smdCapTolerance", "smdCapSvg", "smdCapSpec",
        "indBandSelects", "indValueInput", "indSvg", "indResult", "indBodyColor",
        "previewInput",
    ];

    static values = {
        endpoint: String,
        csrf: String,
        prefillOhms: { type: Number, default: 0 },
        prefillFarads: { type: Number, default: 0 },
    };

    connect() {
        this.bandCount = 5;
        this.renderBandSelects();
        this.updateCapSpec();

        // When opened from a part, pre-fill with the part's detected resistance/capacitance;
        // otherwise fall back to illustrative demo values so nothing starts empty. Each section
        // is isolated so a failure in one can't block the others (and surfaces on screen).
        const partOhms = this.prefillOhmsValue > 0 ? this.prefillOhmsValue : null;
        const partFarads = this.prefillFaradsValue > 0 ? this.prefillFaradsValue : null;

        const resistorOhms = partOhms ?? 4700;
        if (!this.setBandsFromValue(resistorOhms, 1) && this.hasResistorValueInputTarget) {
            // Not representable as standard color bands: show it in the value input instead.
            this.resistorValueInputTarget.value = this.formatOhms(resistorOhms);
        }
        this.updateResistor();

        try {
            this.smdMarking = "code3";
            // 10 kΩ demo has a clean code in every representation (103 / 1002 / 01C) so the
            // EIA-96 field isn't "—" on first open, unlike an E24 value such as 4.7 kΩ.
            this.smdOhms = partOhms ?? 10000;
            this.setSmdFields(this.smdOhms, null);
            this.redrawSmd();
        } catch (e) {
            console.error("value_calc: SMD init failed", e);
            if (this.hasSmdResultTarget) this.smdResultTarget.textContent = "error: " + e.message;
        }
        try {
            this.capPf = (partFarads ?? 100e-9) * 1e12;
            this.setCapFields(this.capPf, null);
            this.redrawCap();
        } catch (e) {
            console.error("value_calc: capacitor init failed", e);
            if (this.hasCapResultTarget) this.capResultTarget.textContent = "error: " + e.message;
        }
        try {
            //THT inductor colour-band tab: 4 bands read as µH, demo value 100 µH.
            this.indBandCount = 4;
            if (this.hasIndBandSelectsTarget) {
                this.renderBandSelects(this.indBandSelectsTarget, this.indBandCount, "updateInductor");
                this.setBandsFromValue(100, 10, this.indBandSelectsTarget, this.indBandCount);
                this.updateInductor();
            }
        } catch (e) {
            console.error("value_calc: THT inductor init failed", e);
        }
        try {
            //The SMD inductor tab starts on an illustrative value so it isn't empty on first open.
            this.syncSmdInductor();
        } catch (e) {
            console.error("value_calc: SMD inductor init failed", e);
        }
        try {
            this.syncSmdCap();
        } catch (e) {
            console.error("value_calc: SMD capacitor init failed", e);
        }

        // Jump to the tab matching the part's detected type.
        if (partFarads !== null && partOhms === null) {
            this.activateTab("vc-capacitor-tab");
        } else if (partOhms !== null) {
            this.activateTab("vc-resistor-tab");
        }
    }

    /** Activates a Bootstrap tab by its button id (no-op if unavailable). */
    activateTab(id) {
        const btn = document.getElementById(id);
        if (!btn) {
            return;
        }
        try {
            btn.click();
        } catch (e) {
            /* ignore — the default tab is fine */
        }
    }

    /**
     * Public helper used by the bulk generator: renders the picture for the given component and
     * returns its SVG markup (without any tab interaction). `type` is 'resistor', 'smd_resistor'
     * or 'capacitor'; `value` is ohms (resistors) or farads (capacitors); `options` may carry
     * {voltage, package, tolerance}.
     */
    generateSvg(type, value, options = {}) {
        try {
            if (options.bodyColor) {
                if (this.hasCapBodyColorTarget) {
                    this.capBodyColorTarget.value = options.bodyColor;
                }
                if (this.hasSmdBodyColorTarget) {
                    this.smdBodyColorTarget.value = options.bodyColor;
                }
                if (this.hasResistorBodyColorTarget) {
                    this.resistorBodyColorTarget.value = options.bodyColor;
                }
            }
            if (type === "capacitor") {
                if (this.hasCapDiameterTarget && options.diameter > 0) {
                    this.capDiameterTarget.value = String(options.diameter);
                }
                if (this.hasCapPitchTarget && options.pitch) {
                    this.capPitchTarget.value = String(options.pitch);
                }
                if (this.hasCapVoltageTarget) {
                    this.capVoltageTarget.value = options.voltage > 0 ? String(options.voltage) : "";
                }
                if (this.hasCapShapeTarget && options.shape) {
                    this.capShapeTarget.value = options.shape;
                }
                if (this.hasCapLeadTarget && options.leadLength) {
                    this.capLeadTarget.value = options.leadLength;
                }
                if (this.hasCapToleranceTarget) {
                    this.capToleranceTarget.value = options.tolerance ? this.capToleranceLetterForPercent(options.tolerance) : "";
                }
                this.updateCapSpec();
                this.capPf = value * 1e12;
                this.setCapFields(this.capPf, null);
                this.redrawCap();
                return this.hasCapSvgTarget ? this.capSvgTarget.innerHTML.trim() : "";
            }
            if (type === "smd_resistor" || type === "smd") {
                if (options.package && this.hasSmdPackageTarget) {
                    this.smdPackageTarget.value = options.package;
                }
                //On SMD resistors the tolerance is expressed by the marking system: 1% (or tighter)
                //uses the 4-digit code, looser tolerances use the 3-digit code. Fall back to 3-digit
                //if the 4-digit code can't represent the value.
                const wants4 = options.tolerance != null && options.tolerance <= 1;
                this.smdMarking = wants4 && this.ohmsTo4Digit(value) ? "code4" : "code3";
                this.smdOhms = value;
                this.smdTolerance = options.tolerance;
                this.smdVoltage = options.voltage;
                this.setSmdFields(value, null);
                this.redrawSmd();
                return this.hasSmdSvgTarget ? this.smdSvgTarget.innerHTML.trim() : "";
            }
            if (type === "inductor") {
                //The inductor colour code is the resistor code read as microhenries.
                if (options.leadLength) {
                    this.resistorLead = options.leadLength;
                }
                const desiredBands = (options.tolerance != null && options.tolerance <= 2) ? 5 : 4;
                if (this.hasBandSelectsTarget && this.bandCount !== desiredBands) {
                    this.bandCount = desiredBands;
                    this.renderBandSelects();
                }
                this.setBandsFromValue(value / 1e-6, options.tolerance ?? 10);
                this.drawInductor(this.selectedColors(), value, options.bodyColor, null, null, {tolerance: options.tolerance, voltage: options.voltage});
                return this.hasResistorSvgTarget ? this.resistorSvgTarget.innerHTML.trim() : "";
            }
            if (type === "smd_inductor") {
                //Molded/shielded SMD power inductor: the printed marking is the 3-digit EIA code in µH.
                const marking = this.henriesToInductorCode(value / 1e-6);
                const t = this.hasSmdIndSvgTarget ? this.smdIndSvgTarget : this.smdSvgTarget;
                this.drawSmdInductor(t, marking, value, options);
                return t ? t.innerHTML.trim() : "";
            }
            if (type === "smd_capacitor") {
                //MLCC chip: unmarked, value shown as a caption. `value` is farads.
                this.smdCapPf = value * 1e12;
                const t = this.hasSmdCapSvgTarget ? this.smdCapSvgTarget : this.capSvgTarget;
                this.drawSmdCapacitor(t, {package: options.package || "0805", bodyColor: options.bodyColor, voltage: options.voltage, tolerance: options.tolerance});
                return t ? t.innerHTML.trim() : "";
            }
            if (type === "diode") {
                //Bulk-only type (no interactive tab): draws into the shared scratch target, like the inductor.
                this.drawDiode(this.resistorSvgTarget, options.subtype || "diode", value, options);
                return this.hasResistorSvgTarget ? this.resistorSvgTarget.innerHTML.trim() : "";
            }
            // Resistor (through-hole colour bands)
            this.resistorVoltage = options.voltage; //shown on the picture when the part lists a rated voltage
            if (this.hasResistorPowerTarget && options.power) {
                this.resistorPowerTarget.value = this.resistorPowerKey(options.power);
            }
            if (options.leadLength) {
                this.resistorLead = options.leadLength;
            }
            //Band count follows real convention: 6 bands when a temp coefficient is given,
            //5 bands for tight tolerance (≤2 %), otherwise 4 bands.
            const desiredBands = options.ppm ? 6 : ((options.tolerance != null && options.tolerance <= 2) ? 5 : 4);
            if (this.hasBandSelectsTarget && this.bandCount !== desiredBands) {
                this.bandCount = desiredBands;
                this.renderBandSelects();
            }
            if (!this.setBandsFromValue(value, options.tolerance ?? 5) && this.hasResistorValueInputTarget) {
                this.resistorValueInputTarget.value = this.formatOhms(value);
            }
            if (options.ppm && this.hasBandSelectsTarget) {
                this.applyTempBand(options.ppm);
            }
            this.updateResistor();
            return this.hasResistorSvgTarget ? this.resistorSvgTarget.innerHTML.trim() : "";
        } catch (e) {
            console.error("value_calc: generateSvg failed", e);
            return "";
        }
    }

    /**
     * Empties the shared preview SVG targets. Bulk previews call this after copying each generated
     * SVG into its own cell, so the last-rendered SVG isn't left here with an id that then collides
     * with the copy in the (visible) cell — which made the last preview render unclipped.
     */
    clearScratchSvg() {
        if (this.hasCapSvgTarget) {
            this.capSvgTarget.innerHTML = "";
        }
        if (this.hasSmdSvgTarget) {
            this.smdSvgTarget.innerHTML = "";
        }
        if (this.hasResistorSvgTarget) {
            this.resistorSvgTarget.innerHTML = "";
        }
        if (this.hasSmdIndSvgTarget) {
            this.smdIndSvgTarget.innerHTML = "";
        }
        if (this.hasSmdCapSvgTarget) {
            this.smdCapSvgTarget.innerHTML = "";
        }
    }

    /**
     * Posts the currently shown SVG of the chosen picture to the server so it gets attached to
     * the part. Uses a background request so the modal can close without navigating away (which
     * would otherwise trigger the browser's "unsaved changes" prompt and lose the edit form).
     */
    attachToPart(event) {
        const {svg, name} = this.activeSvgAndName();
        this.doAttach(svg, name, event.currentTarget);
    }

    /** Reads the SVG markup and label of the currently active tab's preview picture. */
    activeSvgAndName() {
        const active = this.element.querySelector(".tab-pane.active");
        if (!active) {
            return {svg: "", name: ""};
        }
        const containers = {
            "vc-resistor": this.hasResistorSvgTarget ? this.resistorSvgTarget : null,
            "vc-capacitor": this.hasCapSvgTarget ? this.capSvgTarget : null,
            "vc-smd": this.hasSmdSvgTarget ? this.smdSvgTarget : null,
            "vc-inductor": this.hasIndSvgTarget ? this.indSvgTarget : null,
            "vc-smdind": this.hasSmdIndSvgTarget ? this.smdIndSvgTarget : null,
            "vc-smdcap": this.hasSmdCapSvgTarget ? this.smdCapSvgTarget : null,
        };
        const container = containers[active.id];
        const svg = container ? container.innerHTML.trim() : "";
        const name = active.dataset.vcName || "Generated image";
        return {svg, name};
    }

    /** Downloads the SVG shown in the currently active tab as a standalone .svg file. */
    downloadSvg() {
        const {svg, name} = this.activeSvgAndName();
        if (!svg.includes("<svg")) {
            AlertSwal.fire({title: trans("tools.value_calc.attach.nothing")});
            return;
        }

        const withHeader = svg.startsWith("<?xml") ? svg : `<?xml version="1.0" encoding="UTF-8"?>\n${svg}`;
        const blob = new Blob([withHeader], {type: "image/svg+xml"});
        const url = URL.createObjectURL(blob);
        const filename = `${(name || "component").toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_+|_+$/g, "") || "component"}.svg`;

        const link = document.createElement("a");
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    /** Sends one SVG to the server to be attached to the part (background request, no navigation). */
    doAttach(svg, name, btn) {
        if (!this.hasEndpointValue) {
            return;
        }
        if (!svg.includes("<svg")) {
            AlertSwal.fire({title: trans("tools.value_calc.attach.nothing")});
            return;
        }

        const preview = this.hasPreviewInputTarget ? this.previewInputTarget.checked : true;
        const body = new FormData();
        body.append("svg", svg);
        body.append("name", name || "");
        body.append("preview", preview ? "1" : "0");
        body.append("_token", this.csrfValue);

        if (btn) {
            btn.disabled = true;
        }
        fetch(this.endpointValue, {
            method: "POST",
            body,
            headers: {"X-Requested-With": "XMLHttpRequest"},
        })
            .then((r) => r.json().then((data) => ({ok: r.ok, data})))
            .then(({ok, data}) => {
                if (btn) {
                    btn.disabled = false;
                }
                if (ok && data && data.success) {
                    this.finishAttach(data.message);
                } else {
                    AlertSwal.fire({title: (data && data.message) || trans("tools.value_calc.invalid_input")});
                }
            })
            .catch(() => {
                if (btn) {
                    btn.disabled = false;
                }
                AlertSwal.fire({title: trans("tools.value_calc.invalid_input")});
            });
    }

    /**
     * After a successful attach: close the generator modal (via its dismiss control, which works
     * even when Bootstrap isn't exposed globally), then either reload the read-only part page so
     * the new picture shows, or — on the edit form — just toast so unsaved changes aren't lost.
     */
    finishAttach(message) {
        const modalEl = document.getElementById("vcGenerateModal");
        if (modalEl) {
            const dismiss = modalEl.querySelector("[data-bs-dismiss='modal']");
            if (dismiss) {
                dismiss.click();
            } else {
                window.bootstrap?.Modal?.getInstance(modalEl)?.hide();
            }
        }

        // On the edit page, refresh just the attachment list via its Turbo frame: the new image
        // shows and the form includes it (so orphanRemoval can't delete it on the next save) —
        // without a full-page reload or the unsaved-changes prompt. Elsewhere (part info page)
        // just reload so the new picture appears.
        const frame = document.getElementById("part-attachments-frame");
        if (frame) {
            if (frame.getAttribute("src") && typeof frame.reload === "function") {
                frame.reload();
            } else {
                frame.setAttribute("src", window.location.href.split("#")[0]);
            }
            AlertSwal.fire({title: message, icon: "success", timer: 2000, showConfirmButton: false});
        } else {
            window.location.reload();
        }
    }

    /*
     * ---------------------------------------------------------------
     *  Resistor color code
     * ---------------------------------------------------------------
     */

    changeBandCount(event) {
        // Read the currently shown value BEFORE changing bandCount: computeResistance() reads
        // this.bandCount against the still-old (not yet re-rendered) selects, so it must run while
        // both are still in sync — otherwise the role count no longer matches the select count and
        // the value is silently lost (bands reset to their defaults instead of being preserved).
        const current = this.computeResistance();
        this.bandCount = parseInt(event.target.value, 10);
        this.renderBandSelects();
        if (current && current.ohms > 0) {
            this.setBandsFromValue(current.ohms, current.tolerance);
        }
        this.updateResistor();
    }

    /** Returns the list of band "roles" for the given band count (defaults to the resistor tab's). */
    bandRoles(count = this.bandCount) {
        if (count === 4) {
            return ["digit", "digit", "multiplier", "tolerance"];
        }
        if (count === 6) {
            return ["digit", "digit", "digit", "multiplier", "tolerance", "temp"];
        }
        return ["digit", "digit", "digit", "multiplier", "tolerance"];
    }

    /** Colors that are valid for a given band role. */
    colorsForRole(role) {
        return Object.keys(RESISTOR_COLORS).filter((name) => RESISTOR_COLORS[name][role] !== null);
    }

    labelForRole(role) {
        return {
            digit: "tools.value_calc.resistor.band_digit",
            multiplier: "tools.value_calc.resistor.band_multiplier",
            tolerance: "tools.value_calc.resistor.band_tolerance",
            temp: "tools.value_calc.resistor.band_temp",
        }[role];
    }

    renderBandSelects(target = this.bandSelectsTarget, count = this.bandCount, action = "updateResistor") {
        const roles = this.bandRoles(count);
        let html = "";
        //Each band gets an ordinal prefix ("1st", "2nd", …) so the three identical "Digit" bands are
        //no longer ambiguous — the number matches reading the physical part left-to-right.
        roles.forEach((role, index) => {
            const options = this.colorsForRole(role)
                .map((name) => `<option value="${name}">${this.colorLabel(name)}</option>`)
                .join("");
            const col = roles.length >= 6 ? "col" : "col-sm";
            const label = `${this.ordinal(index + 1)} ${trans("tools.value_calc.band")} · ${trans(this.labelForRole(role))}`;
            html += `
                <div class="${col} mb-2">
                    <label class="form-label small text-muted mb-1">${label}</label>
                    <select class="form-select" data-band-index="${index}"
                            data-action="${this.identifier}#${action}">${options}</select>
                </div>`;
        });
        target.innerHTML = html;
    }

    /** English ordinal for a small band index (1 -> "1st", 2 -> "2nd", 3 -> "3rd", 4 -> "4th", …). */
    ordinal(n) {
        if (n === 1) {
            return "1st";
        }
        if (n === 2) {
            return "2nd";
        }
        if (n === 3) {
            return "3rd";
        }
        return `${n}th`;
    }

    /**
     * Builds the " · 50 V · ±10%" spec suffix appended to the value printed on a generated picture,
     * so the image also carries the rated voltage (caps) and tolerance when they are known. Parts
     * that don't apply are simply omitted.
     */
    specSuffix(opts = {}) {
        let s = "";
        const v = opts.voltage !== undefined && opts.voltage !== null ? parseFloat(opts.voltage) : NaN;
        if (Number.isFinite(v) && v > 0) {
            s += ` · ${this.trimNumber(v)} V`;
        }
        const t = opts.tolerance !== undefined && opts.tolerance !== null && opts.tolerance !== "" ? parseFloat(opts.tolerance) : NaN;
        if (Number.isFinite(t) && t > 0) {
            s += ` · ±${this.trimNumber(t)}%`;
        }
        return s;
    }

    colorLabel(name) {
        return trans("tools.value_calc.color." + name);
    }

    /** Reads the currently selected color of every band select in the given target. */
    selectedColorsFrom(target) {
        return Array.from(target.querySelectorAll("select")).map((sel) => sel.value);
    }

    /** Reads the currently selected color of every resistor band select. */
    selectedColors() {
        return this.selectedColorsFrom(this.bandSelectsTarget);
    }

    /** Reads a band-coded value (digits × multiplier), tolerance % and temp ppm from a selects target. */
    computeBandValue(target, count) {
        const roles = this.bandRoles(count);
        const colors = this.selectedColorsFrom(target);
        if (colors.length !== roles.length) {
            return null;
        }

        let digits = "";
        let multiplier = 1;
        let tolerance = null;
        let temp = null;

        roles.forEach((role, i) => {
            const color = RESISTOR_COLORS[colors[i]];
            if (role === "digit") {
                digits += color.digit.toString();
            } else if (role === "multiplier") {
                multiplier = color.multiplier;
            } else if (role === "tolerance") {
                tolerance = color.tolerance;
            } else if (role === "temp") {
                temp = color.temp;
            }
        });

        return {value: parseInt(digits, 10) * multiplier, tolerance, temp};
    }

    computeResistance() {
        const r = this.computeBandValue(this.bandSelectsTarget, this.bandCount);
        return r === null ? null : {ohms: r.value, tolerance: r.tolerance, temp: r.temp};
    }

    updateResistor() {
        const res = this.computeResistance();
        if (!res) {
            return;
        }

        let text = this.formatOhms(res.ohms);
        if (res.tolerance !== null) {
            text += ` ±${res.tolerance}%`;
        }
        if (res.temp !== null) {
            text += ` · ${res.temp} ppm/K`;
        }
        if (this.hasResistorResultTarget) {
            this.resistorResultTarget.textContent = text;
        }
        this.drawResistor(this.selectedColors());
    }

    /**
     * Determine the band colors representing the given resistance and write
     * them into the selects.
     */
    setBandsFromValue(value, tolerance, target = this.bandSelectsTarget, count = this.bandCount) {
        if (!(value > 0)) {
            return false;
        }
        const numDigits = count === 4 ? 2 : 3;

        // Normalize the value into <numDigits> significant figures + power of ten
        let exp = Math.floor(Math.log10(value)) - (numDigits - 1);
        let digits = Math.round(value / Math.pow(10, exp));
        if (digits >= Math.pow(10, numDigits)) {
            digits = Math.round(digits / 10);
            exp += 1;
        }
        const multiplier = Math.pow(10, exp);

        // Find a color whose multiplier matches (within float tolerance)
        const multiplierColor = Object.keys(RESISTOR_COLORS).find(
            (name) => RESISTOR_COLORS[name].multiplier !== null
                && Math.abs(RESISTOR_COLORS[name].multiplier - multiplier) < multiplier * 1e-6
        );
        if (!multiplierColor) {
            // Value out of representable range
            return false;
        }

        const digitStr = digits.toString().padStart(numDigits, "0");
        const roles = this.bandRoles(count);
        const selects = target.querySelectorAll("select");
        let digitIdx = 0;
        roles.forEach((role, i) => {
            if (role === "digit") {
                selects[i].value = this.colorForDigit(parseInt(digitStr[digitIdx], 10));
                digitIdx += 1;
            } else if (role === "multiplier") {
                selects[i].value = multiplierColor;
            } else if (role === "tolerance" && tolerance !== null && tolerance !== undefined) {
                const tolColor = this.colorForTolerance(tolerance);
                if (tolColor) {
                    selects[i].value = tolColor;
                }
            }
        });
        return true;
    }

    colorForDigit(digit) {
        return Object.keys(RESISTOR_COLORS).find((name) => RESISTOR_COLORS[name].digit === digit);
    }

    colorForTolerance(tolerance) {
        return Object.keys(RESISTOR_COLORS).find(
            (name) => RESISTOR_COLORS[name].tolerance === tolerance
        );
    }

    /** Snaps a wattage to the nearest defined resistor power rating key (e.g. 0.3 -> "0.25"). */
    resistorPowerKey(watts) {
        const keys = Object.keys(RESISTOR_POWERS).map(Number);
        let best = keys[0];
        for (const k of keys) {
            if (Math.abs(k - watts) < Math.abs(best - watts)) {
                best = k;
            }
        }
        return String(best);
    }

    /** The band colour whose temperature coefficient is nearest to the given ppm/K value. */
    colorForTemp(ppm) {
        let best = null;
        let bestDelta = Infinity;
        for (const name of Object.keys(RESISTOR_COLORS)) {
            const t = RESISTOR_COLORS[name].temp;
            if (t === null || t === undefined) {
                continue;
            }
            const delta = Math.abs(t - ppm);
            if (delta < bestDelta) {
                bestDelta = delta;
                best = name;
            }
        }
        return best;
    }

    /** Sets the temperature-coefficient band (6-band resistors) to the colour matching the ppm value. */
    applyTempBand(ppm) {
        const roles = this.bandRoles();
        const selects = this.bandSelectsTarget.querySelectorAll("select");
        const tempColor = this.colorForTemp(ppm);
        roles.forEach((role, i) => {
            if (role === "temp" && tempColor && selects[i]) {
                selects[i].value = tempColor;
            }
        });
    }

    applyResistorValue() {
        const raw = this.resistorValueInputTarget.value;
        const ohms = this.parseValue(raw, "R");
        if (ohms === null || !(ohms > 0)) {
            this.resistorValueInputTarget.classList.add("is-invalid");
            return;
        }
        // Keep whatever tolerance is currently selected, default to 1%
        const current = this.computeResistance();
        const tol = current && current.tolerance !== null ? current.tolerance : 1;
        if (!this.setBandsFromValue(ohms, tol)) {
            this.resistorValueInputTarget.classList.add("is-invalid");
            return;
        }
        this.resistorValueInputTarget.classList.remove("is-invalid");
        this.updateResistor();
    }

    applyResistorBodyColor(event) {
        if (this.hasResistorBodyColorTarget) {
            this.resistorBodyColorTarget.value = event.currentTarget.dataset.color;
        }
        this.updateResistor();
    }

    /*
     * ---------------------------------------------------------------
     *  THT inductor colour code (interactive) — same bands as a resistor, read as µH.
     * ---------------------------------------------------------------
     */

    changeIndBandCount(event) {
        //Same ordering requirement as changeBandCount(): read the value while the (still-old) DOM
        //and the (still-old) band count agree, before switching the count and re-rendering.
        const current = this.computeBandValue(this.indBandSelectsTarget, this.indBandCount);
        this.indBandCount = parseInt(event.target.value, 10);
        this.renderBandSelects(this.indBandSelectsTarget, this.indBandCount, "updateInductor");
        if (current && current.value > 0) {
            this.setBandsFromValue(current.value, current.tolerance, this.indBandSelectsTarget, this.indBandCount);
        }
        this.updateInductor();
    }

    /** Reads the inductor band colours, computes the µH value and redraws the barrel. */
    updateInductor() {
        if (!this.hasIndSvgTarget) {
            return;
        }
        const r = this.computeBandValue(this.indBandSelectsTarget, this.indBandCount);
        if (!r) {
            return;
        }
        const henries = r.value * 1e-6; //the band value is read in microhenries
        let text = this.formatHenries(henries);
        if (r.tolerance !== null) {
            text += ` ±${r.tolerance}%`;
        }
        if (this.hasIndResultTarget) {
            this.indResultTarget.textContent = text;
        }
        const color = this.hasIndBodyColorTarget ? this.indBodyColorTarget.value : null;
        this.drawInductor(this.selectedColorsFrom(this.indBandSelectsTarget), henries, color, this.indSvgTarget, "medium", {tolerance: r.tolerance});
    }

    /** Sets the inductor bands from a typed inductance (bare number = µH; accepts nH/µH/mH/H). */
    applyInductorValue() {
        const raw = (this.hasIndValueInputTarget ? this.indValueInputTarget.value : "").trim();
        const m = raw.match(/^([\d.]+)\s*(p|n|u|µ|m)?\s*h?$/i);
        if (!m) {
            if (this.hasIndValueInputTarget) {
                this.indValueInputTarget.classList.add("is-invalid");
            }
            return;
        }
        const num = parseFloat(m[1]);
        const factors = {p: 1e-12, n: 1e-9, u: 1e-6, "µ": 1e-6, m: 1e-3};
        const henries = m[2] ? num * factors[m[2].toLowerCase()] : num * 1e-6; //bare number = µH
        const uH = henries / 1e-6;
        const current = this.computeBandValue(this.indBandSelectsTarget, this.indBandCount);
        const tol = current && current.tolerance !== null ? current.tolerance : 10;
        if (!(uH > 0) || !this.setBandsFromValue(uH, tol, this.indBandSelectsTarget, this.indBandCount)) {
            if (this.hasIndValueInputTarget) {
                this.indValueInputTarget.classList.add("is-invalid");
            }
            return;
        }
        if (this.hasIndValueInputTarget) {
            this.indValueInputTarget.classList.remove("is-invalid");
        }
        this.updateInductor();
    }

    applyIndBodyColor(event) {
        if (this.hasIndBodyColorTarget) {
            this.indBodyColorTarget.value = event.currentTarget.dataset.color;
        }
        this.updateInductor();
    }

    /** Draws a 3D-shaded axial resistor SVG with bands and dimension callouts. */
    drawResistor(colors) {
        const uid = this.svgId();
        const margin = 6;
        const leadExt = RESISTOR_LEAD_LENGTHS[this.resistorLeadValue()] ?? RESISTOR_LEAD_LENGTHS.medium;
        const bodyW = 208;
        const bodyH = 66;
        const bodyX = margin + leadExt;
        const width = bodyW + 2 * (margin + leadExt);
        const height = 205;
        const cy = 60;
        const bodyY = cy - bodyH / 2;
        const bodyBottom = bodyY + bodyH;

        // Distribute the bands across the body, leaving the tolerance band set apart
        const n = colors.length;
        const bandW = 16;
        const leftPad = 22;
        const rightPad = 32; // extra gap before the tolerance band
        const usable = bodyW - leftPad - rightPad;
        const step = usable / (n - 1);

        let bands = "";
        colors.forEach((name, i) => {
            const c = RESISTOR_COLORS[name];
            // Put the last band (tolerance/temp) towards the right end
            let x = bodyX + leftPad + i * step;
            if (i === n - 1) {
                x = bodyX + bodyW - rightPad + 8;
            }
            bands += `<rect x="${x - bandW / 2}" y="${bodyY - 2}" width="${bandW}" height="${bodyH + 4}" fill="${c.hex}"/>`;
        });

        const body = this.safeColor(this.bodyColor(this.hasResistorBodyColorTarget ? this.resistorBodyColorTarget : null, "#d8c7a0"), "#d8c7a0");
        const dim = RESISTOR_POWERS[this.resistorPowerValue()];
        //The bands are the "real" value encoding, but printing the decoded value too (like every
        //other drawing in this tool) makes the picture self-explanatory on its own.
        const res = this.computeResistance();
        const resistanceLabel = res ? this.formatOhms(res.ohms) + this.specSuffix({voltage: this.resistorVoltage, tolerance: res.tolerance}) : "";
        const callouts =
            this.dimH(bodyX, bodyX + bodyW, bodyBottom + 16, `L ${this.formatMm(dim.len)}`)
            + this.dimH(margin + 4, width - margin - 4, height - 34, `pitch ${this.formatMm(dim.pitch)} (${dim.pitchIn})`)
            + this.dimV(bodyY, bodyBottom, bodyX + bodyW + 28, `⌀ ${this.formatMm(dim.dia)}`, bodyX + bodyW)
            + (resistanceLabel ? `<text x="${width / 2}" y="${height - 10}" text-anchor="middle" font-family="monospace" font-size="16" font-weight="700" fill="#3a4149">${resistanceLabel}</text>` : "");

        const svg = `
        <svg viewBox="0 0 ${width} ${height}" xmlns="http://www.w3.org/2000/svg" style="max-width: 460px; width: 100%; height: auto;">
            <defs>
                ${this.leadGradient(uid)}
                ${this.cylinderGradient(uid)}
                ${this.endVignetteGradient(uid)}
                ${this.blurFilter(uid)}
                <clipPath id="${uid}clip"><rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" rx="20" ry="20"/></clipPath>
                ${this.shadowFilter(uid)}
            </defs>
            <g>
                <rect x="${margin}" y="${cy - 5}" width="${width - 2 * margin}" height="10" rx="5" fill="url(#${uid}lead)"/>
                <g clip-path="url(#${uid}clip)">
                    <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" fill="${body}"/>
                    ${bands}
                    <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" fill="url(#${uid}cyl)"/>
                    <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" fill="url(#${uid}vig)"/>
                    <ellipse cx="${width / 2}" cy="${bodyY + bodyH * 0.26}" rx="${bodyW * 0.44}" ry="4.5" fill="#ffffff" opacity="0.45" filter="url(#${uid}blur)"/>
                </g>
                <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" rx="20" ry="20" fill="none" stroke="#00000055" stroke-width="1"/>
            </g>
            ${callouts}
        </svg>`;
        this.resistorSvgTarget.innerHTML = svg;

        if (this.hasResistorSpecTarget) {
            this.resistorSpecTarget.textContent =
                `${dim.label} · ${this.formatMm(dim.len)} × ⌀${this.formatMm(dim.dia)} · pitch ${this.formatMm(dim.pitch)} (${dim.pitchIn})`;
        }
    }

    /**
     * Draws a molded axial inductor: the same colour-band cylinder as a resistor, but a fatter
     * green body and a henry value label. The bands are set by the shared resistor band engine
     * (the inductor colour code is identical, read as microhenries).
     */
    drawInductor(colors, henries, bodyColorOverride, target = null, leadKey = null, spec = {}) {
        const tgt = target || this.resistorSvgTarget;
        const uid = this.svgId();
        const margin = 6;
        const leadExt = RESISTOR_LEAD_LENGTHS[leadKey || this.resistorLeadValue()] ?? RESISTOR_LEAD_LENGTHS.medium;
        const bodyW = 168;
        const bodyH = 78;
        const bodyX = margin + leadExt;
        const width = bodyW + 2 * (margin + leadExt);
        const height = 196;
        const cy = 62;
        const bodyY = cy - bodyH / 2;
        const bodyBottom = bodyY + bodyH;

        const n = colors.length;
        const bandW = 16;
        const leftPad = 22;
        const rightPad = 32;
        const usable = bodyW - leftPad - rightPad;
        const step = usable / (n - 1);
        let bands = "";
        colors.forEach((name, i) => {
            const c = RESISTOR_COLORS[name];
            let x = bodyX + leftPad + i * step;
            if (i === n - 1) {
                x = bodyX + bodyW - rightPad + 8;
            }
            bands += `<rect x="${x - bandW / 2}" y="${bodyY - 2}" width="${bandW}" height="${bodyH + 4}" fill="${c.hex}"/>`;
        });

        const colorTarget = bodyColorOverride ? {value: bodyColorOverride} : (this.hasResistorBodyColorTarget ? this.resistorBodyColorTarget : null);
        const body = this.safeColor(this.bodyColor(colorTarget, "#2f6f4c"), "#2f6f4c");
        //A colour-coded THT inductor's physical size isn't implied by its inductance, so we don't
        //draw a (fake) dimension callout here — just the decoded value below the barrel.
        const callouts =
            `<text x="${width / 2}" y="${height - 12}" text-anchor="middle" font-family="monospace" font-size="16" font-weight="700" fill="#3a4149">${this.formatHenries(henries)}${this.specSuffix(spec)}</text>`;

        tgt.innerHTML = `
        <svg viewBox="0 0 ${width} ${height}" xmlns="http://www.w3.org/2000/svg" style="max-width: 460px; width: 100%; height: auto;">
            <defs>
                ${this.leadGradient(uid)}
                ${this.cylinderGradient(uid)}
                ${this.endVignetteGradient(uid)}
                ${this.blurFilter(uid)}
                <clipPath id="${uid}clip"><rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" rx="30" ry="30"/></clipPath>
                ${this.shadowFilter(uid)}
            </defs>
            <g>
                <rect x="${margin}" y="${cy - 5}" width="${width - 2 * margin}" height="10" rx="5" fill="url(#${uid}lead)"/>
                <g clip-path="url(#${uid}clip)">
                    <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" fill="${body}"/>
                    ${bands}
                    <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" fill="url(#${uid}cyl)"/>
                    <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" fill="url(#${uid}vig)"/>
                    <ellipse cx="${width / 2}" cy="${bodyY + bodyH * 0.24}" rx="${bodyW * 0.44}" ry="5" fill="#ffffff" opacity="0.4" filter="url(#${uid}blur)"/>
                </g>
                <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" rx="30" ry="30" fill="none" stroke="#00000055" stroke-width="1"/>
            </g>
            ${callouts}
        </svg>`;
    }

    /** Human-readable inductance: nH / µH / mH / H. */
    formatHenries(h) {
        if (h >= 1) {
            return `${this.trimNumber(h)} H`;
        }
        if (h >= 1e-3) {
            return `${this.trimNumber(h / 1e-3)} mH`;
        }
        if (h >= 1e-6) {
            return `${this.trimNumber(h / 1e-6)} µH`;
        }
        return `${this.trimNumber(h / 1e-9)} nH`;
    }

    /**
     * The marking printed on an SMD inductor, read in microhenries: R-notation below 10 µH
     * (4.7 -> 4R7, 0.47 -> R47) and the 3-digit EIA code from 10 µH upwards (100 -> 101, 22 -> 220).
     */
    henriesToInductorCode(uH) {
        if (!(uH > 0)) {
            return "";
        }
        if (uH < 10) {
            let s = parseFloat(uH.toFixed(2)).toString();
            if (!s.includes(".")) {
                s += ".0";
            }
            return s.startsWith("0.") ? "R" + s.slice(2) : s.replace(".", "R");
        }
        let exp = Math.floor(Math.log10(uH)) - 1;
        let significant = Math.round(uH / Math.pow(10, exp));
        if (significant >= 100) {
            significant = Math.round(significant / 10);
            exp += 1;
        }
        if (exp < 0) {
            exp = 0;
        }
        return significant.toString().padStart(2, "0") + exp.toString();
    }

    /**
     * Draws a molded / shielded SMD power inductor: a dark rounded ferrite block with a soft domed
     * highlight, metal end terminations and the printed µH marking. Sized from the chip package.
     */
    drawSmdInductor(target, marking, henries, options = {}) {
        const uid = this.svgId();
        const w = 300;
        const pkgKey = SMD_PACKAGES[options.package] ? options.package : "1210";
        const pkg = SMD_PACKAGES[pkgKey];

        //Simple top-down chip with the value code printed on it (same style as the SMD resistor); the
        //body follows the package L:W ratio so a 1210 looks square and a 0402 a 2:1 rectangle.
        const bodyW = Math.round(122 + 66 * (pkg.l - 0.6) / (6.3 - 0.6));
        const aspect = pkg.l / pkg.w;
        const bodyH = Math.max(48, Math.min(140, Math.round(bodyW / aspect)));
        const capW = Math.max(14, Math.round(bodyW * 0.14));
        const cx = w / 2;
        const bodyX = Math.round(cx - bodyW / 2);
        const bodyY = Math.round(84 - bodyH / 2);
        const bodyBottom = bodyY + bodyH;
        const cy = bodyY + bodyH / 2;
        const innerX = bodyX + capW;
        const innerW = bodyW - 2 * capW;

        const fill = this.safeColor(options.bodyColor, "#33363d");
        const textColor = this.contrastColor(fill);
        const fontSize = Math.max(15, Math.min(38, Math.round(bodyH * 0.5), Math.round(innerW * 1.6 / Math.max(3, marking.length))));

        const callouts =
            this.dimH(bodyX, bodyX + bodyW, bodyBottom + 18, `L ${this.formatMm(pkg.l)}`)
            + this.dimV(bodyY, bodyBottom, bodyX + bodyW + 16, `W ${this.formatMm(pkg.w)}`, bodyX + bodyW);

        const h = bodyBottom + 54;
        const valueCaption = `<text x="${cx}" y="${h - 12}" text-anchor="middle" font-family="monospace" font-size="16" font-weight="700" fill="#3a4149">${this.formatHenries(henries)}${this.specSuffix(options)}</text>`;

        target.innerHTML = `
        <svg viewBox="0 0 ${w} ${h}" xmlns="http://www.w3.org/2000/svg" style="max-width: 300px; width: 100%; height: auto;">
            <defs>
                ${this.metalGradient(uid)}
                ${this.glossGradient(uid)}
                <clipPath id="${uid}clip"><rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" rx="10" ry="10"/></clipPath>
                ${this.shadowFilter(uid)}
            </defs>
            <g>
                <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" rx="10" ry="10" fill="url(#${uid}metal)" stroke="#00000055" stroke-width="1" filter="url(#${uid}shadow)"/>
                <g clip-path="url(#${uid}clip)">
                    <rect x="${innerX}" y="${bodyY}" width="${innerW}" height="${bodyH}" fill="${fill}"/>
                    <rect x="${innerX}" y="${bodyY}" width="${innerW}" height="${bodyH}" fill="url(#${uid}gloss)"/>
                    <rect x="${innerX - 2}" y="${bodyY}" width="3" height="${bodyH}" fill="#000000" opacity="0.28"/>
                    <rect x="${innerX + innerW - 1}" y="${bodyY}" width="3" height="${bodyH}" fill="#000000" opacity="0.28"/>
                </g>
                <text x="${cx}" y="${cy}" text-anchor="middle" dominant-baseline="central"
                      font-family="monospace" font-weight="bold" font-size="${fontSize}" fill="${textColor}">${this.escapeXml(marking)}</text>
            </g>
            ${callouts}
            ${valueCaption}
        </svg>`;

        if (options.specEl) {
            options.specEl.textContent =
                `${pkgKey} (${pkg.metric}) · ${this.formatMm(pkg.l)} × ${this.formatMm(pkg.w)} · ${this.formatHenries(henries)}`;
        }
    }

    /**
     * Draws a diode. LEDs become a coloured 5 mm dome (long lead = anode, short lead + flat =
     * cathode); every other kind (rectifier / Zener / Schottky / TVS) becomes an axial body with a
     * cathode band. The kind only changes colour/caption — diode markings aren't standardised.
     */
    drawDiode(target, subtype, voltage, options = {}) {
        if (subtype === "led") {
            this.drawLed(target, this.safeColor(options.bodyColor || options.color, "#c0392b"));
            return;
        }
        this.drawAxialDiode(target, subtype, voltage, options);
    }

    /**
     * Axial diode: a dark glass/epoxy body with a light cathode band near one end and two leads.
     * A recognised part marking (e.g. "1N4001") is printed lengthwise on the body itself, like a
     * real diode — otherwise a small caption below the leads shows the voltage or the diode kind.
     */
    drawAxialDiode(target, subtype, voltage, options = {}) {
        const uid = this.svgId();
        const margin = 6;
        const leadExt = 74;
        const bodyW = 150;
        const bodyH = 64;
        const bodyX = margin + leadExt;
        const width = bodyW + 2 * (margin + leadExt);
        const cy = 58;
        const bodyY = cy - bodyH / 2;
        const bodyBottom = bodyY + bodyH;

        const body = this.safeColor(options.bodyColor, "#20242a");
        //Cathode band (the stripe marking the "line" side of the diode symbol), near the right end.
        const bandW = 15;
        const bandX = bodyX + bodyW - 34;

        const marking = options.marking || null;
        const hasVoltage = voltage && voltage > 0;
        const labels = {diode: "Diode", zener: "Zener", schottky: "Schottky", tvs: "TVS"};
        //Below the body we show: the voltage (if known — e.g. Zener/TVS), else — when there's no
        //part-number marking on the body — the diode kind. So voltage is shown whenever we have it.
        const caption = hasVoltage ? `${this.trimNumber(voltage)} V` : (marking ? "" : (labels[subtype] || "Diode"));

        //Compact canvas only when the body carries a marking AND there's no caption to fit below.
        const height = (marking && caption === "") ? bodyBottom + bodyY : bodyBottom + 30;
        const textColor = this.contrastColor(body);
        const markingFontSize = marking
            ? Math.max(11, Math.min(18, Math.round((bodyW - 8) * 1.7 / Math.max(4, marking.length))))
            : 0;
        const bodyMarking = marking
            ? `<text x="${bodyX + bodyW / 2}" y="${cy}" text-anchor="middle" dominant-baseline="central"
                   font-family="monospace" font-weight="700" font-size="${markingFontSize}" fill="${textColor}"
                   style="paint-order:stroke" stroke="${body}" stroke-width="0.5">${this.escapeXml(marking)}</text>`
            : "";
        const belowCaption = caption === ""
            ? ""
            : `<text x="${width / 2}" y="${height - 10}" text-anchor="middle" font-family="monospace" font-size="15" font-weight="700" fill="#3a4149">${caption}</text>`;

        target.innerHTML = `
        <svg viewBox="0 0 ${width} ${height}" xmlns="http://www.w3.org/2000/svg" style="max-width: 460px; width: 100%; height: auto;">
            <defs>
                ${this.leadGradient(uid)}
                ${this.cylinderGradient(uid)}
                ${this.endVignetteGradient(uid)}
                ${this.blurFilter(uid)}
                <clipPath id="${uid}clip"><rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" rx="16" ry="16"/></clipPath>
                ${this.shadowFilter(uid)}
            </defs>
            <g>
                <rect x="${margin}" y="${cy - 5}" width="${width - 2 * margin}" height="10" rx="5" fill="url(#${uid}lead)"/>
                <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" rx="16" ry="16" fill="${body}" stroke="#00000066" stroke-width="1" filter="url(#${uid}shadow)"/>
                <g clip-path="url(#${uid}clip)">
                    <rect x="${bandX}" y="${bodyY}" width="${bandW}" height="${bodyH}" fill="#e6e9ee"/>
                    <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" fill="url(#${uid}cyl)"/>
                    <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" fill="url(#${uid}vig)"/>
                    <ellipse cx="${bodyX + bodyW * 0.42}" cy="${bodyY + bodyH * 0.26}" rx="${bodyW * 0.34}" ry="4" fill="#ffffff" opacity="0.4" filter="url(#${uid}blur)"/>
                    ${bodyMarking}
                </g>
                <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" rx="16" ry="16" fill="none" stroke="#00000055" stroke-width="1"/>
            </g>
            ${belowCaption}
        </svg>`;
    }

    /** 5 mm through-hole LED: a coloured epoxy dome with a reflector cup, specular highlight and legs. */
    drawLed(target, color) {
        const uid = this.svgId();
        const width = 200;
        const height = 232;
        const cx = 100;
        const domeR = 50;
        const domeTopY = 26;
        const sidesTopY = domeTopY + domeR;
        const rimY = 150;
        const left = cx - domeR;
        const right = cx + domeR;
        const flangeTop = rimY;
        const flangeH = 16;
        const flangeBottom = rimY + flangeH;
        const flangeL = 44;
        const flangeR = 156;

        const anodeX = cx - 18;
        const cathodeX = cx + 18;
        const leadTop = flangeBottom - 2;
        const anodeBottom = height - 30;
        const cathodeBottom = height - 50;

        //Rounded-top body: straight sides up to a hemisphere.
        const bodyPath = `M ${left} ${rimY} L ${left} ${sidesTopY} A ${domeR} ${domeR} 0 0 1 ${right} ${sidesTopY} L ${right} ${rimY} Z`;
        //Flange: rounded on the anode (left) side, flat on the cathode (right) side.
        const flangePath = `M ${flangeL + 6} ${flangeTop} L ${flangeR} ${flangeTop} L ${flangeR} ${flangeBottom} L ${flangeL + 6} ${flangeBottom} Q ${flangeL} ${flangeBottom} ${flangeL} ${flangeBottom - 6} L ${flangeL} ${flangeTop + 6} Q ${flangeL} ${flangeTop} ${flangeL + 6} ${flangeTop} Z`;

        target.innerHTML = `
        <svg viewBox="0 0 ${width} ${height}" xmlns="http://www.w3.org/2000/svg" style="max-width: 260px; width: 100%; height: auto;">
            <defs>
                ${this.leadGradient(uid)}
                <radialGradient id="${uid}led" cx="0.4" cy="0.3" r="0.8">
                    <stop offset="0" stop-color="#ffffff" stop-opacity="0.85"/>
                    <stop offset="0.4" stop-color="${color}" stop-opacity="0.92"/>
                    <stop offset="1" stop-color="${color}"/>
                </radialGradient>
                ${this.blurFilter(uid)}
                ${this.shadowFilter(uid)}
            </defs>
            <g>
                <rect x="${anodeX - 4}" y="${leadTop}" width="8" height="${anodeBottom - leadTop}" rx="3" fill="url(#${uid}lead)"/>
                <rect x="${cathodeX - 4}" y="${leadTop}" width="8" height="${cathodeBottom - leadTop}" rx="3" fill="url(#${uid}lead)"/>
                <path d="${flangePath}" fill="${color}" stroke="#00000055" stroke-width="1" filter="url(#${uid}shadow)"/>
                <path d="${flangePath}" fill="#000000" opacity="0.20"/>
                <path d="${bodyPath}" fill="url(#${uid}led)" stroke="#00000040" stroke-width="1"/>
                <path d="M ${cx - 16} ${rimY - 8} L ${cx + 16} ${rimY - 8} L ${cx + 9} ${rimY - 32} L ${cx - 9} ${rimY - 32} Z" fill="#000000" opacity="0.28"/>
                <rect x="${cx - 4}" y="${rimY - 25}" width="8" height="7" rx="1.5" fill="#fff6c0"/>
                <ellipse cx="${cx - 15}" cy="${domeTopY + 34}" rx="11" ry="24" fill="#ffffff" opacity="0.55" filter="url(#${uid}blur)"/>
            </g>
            <text x="${cx}" y="${height - 8}" text-anchor="middle" font-family="monospace" font-size="15" font-weight="700" fill="#3a4149">LED</text>
        </svg>`;
    }

    resistorPowerValue() {
        const v = this.hasResistorPowerTarget ? this.resistorPowerTarget.value : "0.25";
        return RESISTOR_POWERS[v] ? v : "0.25";
    }

    resistorLeadValue() {
        const v = (this.hasResistorLeadTarget ? this.resistorLeadTarget.value : null) || this.resistorLead || "medium";
        return Object.prototype.hasOwnProperty.call(RESISTOR_LEAD_LENGTHS, v) ? v : "medium";
    }

    /*
     * ---------------------------------------------------------------
     *  Capacitor code
     * ---------------------------------------------------------------
     */

    /** Recomputes the linked value/code fields (and the picture) from whichever was edited. */
    syncCap(event) {
        const field = event.currentTarget.dataset.field;
        const raw = event.currentTarget.value;
        let pf = null;
        if (field === "value") {
            const farads = this.parseValue(raw, "F");
            pf = farads === null ? null : farads * 1e12;
        } else {
            // Split off an optional trailing tolerance letter (e.g. the K in 104K) and,
            // if it is a known code, reflect it in the tolerance selector.
            const up = raw.trim().toUpperCase();
            const letterMatch = up.match(/^([0-9R]+)([A-Z])$/);
            const body = letterMatch ? letterMatch[1] : up;
            if (letterMatch && this.hasCapToleranceTarget && CAP_TOLERANCE[letterMatch[2]]) {
                this.capToleranceTarget.value = letterMatch[2];
            }
            pf = this.capCodeToPf(body);
        }
        if (pf === null || !(pf > 0)) {
            event.currentTarget.classList.add("is-invalid");
            this.capPf = null;
            if (this.hasCapResultTarget) {
                this.capResultTarget.textContent = raw.trim() === "" ? "" : trans("tools.value_calc.invalid_input");
            }
            this.capSvgTarget.innerHTML = "";
            return;
        }
        event.currentTarget.classList.remove("is-invalid");
        this.capPf = pf;
        this.setCapFields(pf, field);
        this.redrawCap();
    }

    /** Writes the value/code fields from a capacitance in pF (skips the field being edited). */
    setCapFields(pf, except) {
        if (except !== "value" && this.hasCapValueInputTarget) {
            this.capValueInputTarget.value = this.formatFarads(pf);
            this.capValueInputTarget.classList.remove("is-invalid");
        }
        if (except !== "code" && this.hasCapCodeInputTarget) {
            const code = this.pfToCapCode(pf);
            this.capCodeInputTarget.value = code ?? "";
            this.capCodeInputTarget.classList.remove("is-invalid");
        }
    }

    /** Draws the capacitor picture (the printed code) plus the value/spec/tolerance text. */
    redrawCap() {
        if (this.capPf === null || this.capPf === undefined || !(this.capPf > 0)) {
            return;
        }
        const code = this.pfToCapCode(this.capPf);
        const letter = this.hasCapToleranceTarget ? this.capToleranceTarget.value : "";
        let text = `${this.formatFarads(this.capPf)} (${this.formatFarads(this.capPf, true)})`;
        const tol = this.capToleranceText();
        if (tol !== "") {
            text += ` · ${trans("tools.value_calc.tolerance")}: ${tol}`;
        }
        if (this.hasCapResultTarget) {
            this.capResultTarget.textContent = text;
        }
        //Real caps print the tolerance letter right after the code (e.g. "104K").
        const marking = code ? (letter ? code + letter : code) : this.formatFarads(this.capPf);
        this.drawCapacitor(this.capSvgTarget, marking);
    }

    /** Maps a tolerance percentage (or small-cap pF value) to its capacitor letter code. */
    capToleranceLetterForPercent(p) {
        const map = {0.1: "B", 0.25: "C", 0.5: "D", 1: "F", 2: "G", 5: "J", 10: "K", 20: "M"};
        return map[p] || "";
    }

    /** Human-readable tolerance for the selected capacitor tolerance letter, or "". */
    capToleranceText() {
        const letter = this.hasCapToleranceTarget ? this.capToleranceTarget.value : "";
        return letter && CAP_TOLERANCE[letter] ? CAP_TOLERANCE[letter] : "";
    }

    /**
     * Converts a printed ceramic/film capacitor code into picofarads.
     * Supports R-notation (4R7 = 4.7 pF), plain 1-2 digit values (47 = 47 pF)
     * and the 3-digit EIA code (104 = 100 nF, with 8/9 as ×0.01/×0.1).
     * Returns null when the code can't be parsed.
     */
    capCodeToPf(code) {
        if (/^\d*R\d*$/.test(code) && code.includes("R")) {
            // R-notation, e.g. 4R7 = 4.7 pF, R47 = 0.47 pF
            const val = parseFloat(code.replace("R", "."));
            return Number.isNaN(val) ? null : val;
        }
        if (/^\d{1,2}$/.test(code)) {
            // Plain value directly in pF (typical for caps below 100 pF)
            return parseInt(code, 10);
        }
        if (/^\d{3}$/.test(code)) {
            const significant = parseInt(code.substring(0, 2), 10);
            const mult = parseInt(code.charAt(2), 10);
            if (mult === 8) {
                return significant * 0.01;
            }
            if (mult === 9) {
                return significant * 0.1;
            }
            return significant * Math.pow(10, mult);
        }
        return null;
    }


    /**
     * Returns the marking that is typically printed on a ceramic capacitor for
     * the given value in picofarads: R-notation below 10 pF, the plain value
     * for 10-99 pF, and the 3-digit EIA code from 100 pF upwards.
     */
    pfToCapCode(pf) {
        if (pf < 10) {
            // R-notation, e.g. 4.7 -> 4R7, 0.47 -> R47
            const s = parseFloat(pf.toFixed(2)).toString();
            if (Number.isInteger(pf)) {
                return s;
            }
            return s.startsWith("0.") ? "R" + s.slice(2) : s.replace(".", "R");
        }
        // Plain value only fits 10-99 pF; values that round up to 100 must use the EIA code below (100 pF -> "101").
        if (Math.round(pf) < 100) {
            return Math.round(pf).toString();
        }
        // Two significant figures + power-of-ten multiplier digit
        let exp = Math.floor(Math.log10(pf)) - 1;
        let significant = Math.round(pf / Math.pow(10, exp));
        if (significant >= 100) {
            significant = Math.round(significant / 10);
            exp += 1;
        }
        if (exp < 0 || exp > 7) {
            return null;
        }
        return significant.toString().padStart(2, "0") + exp.toString();
    }

    /** Draws a ceramic capacitor (radial disc or dipped MLCC blob) with marking and callouts. */
    drawCapacitor(target, marking) {
        const uid = this.svgId();
        const shape = this.capShapeValue();
        const diam = this.capDiameterValue();
        const pitch = this.capPitchValue();
        const pitchIn = CAP_PITCHES[pitch];
        const voltage = this.capVoltageValue();
        const fill = this.safeColor(this.bodyColor(this.hasCapBodyColorTarget ? this.capBodyColorTarget : null, "#e0a63a"), "#e0a63a");
        const textColor = this.contrastColor(fill);
        const shadow = textColor === "#f5f5f5" ? "#00000088" : "#ffffff66";

        const W = 230;
        const cx = W / 2;
        const pxPerMm = 7;
        const topMargin = 34;
        // The body grows with the chosen diameter, within sensible visual bounds.
        const r = Math.max(42, Math.min(96, 52 + (diam - 5) * 4));
        const cy = topMargin + r;
        const pitchPx = Math.max(16, parseFloat(pitch) * pxPerMm);
        const leadX1 = cx - pitchPx / 2;
        const leadX2 = cx + pitchPx / 2;
        const leadExtra = CAP_LEAD_LENGTHS[this.capLeadValue()] ?? CAP_LEAD_LENGTHS.medium;

        // Body outline + highlight geometry for the chosen shape.
        let bodyPath, bodyBottom, gloss, spec, botShadow, topDip, textCy;
        if (shape === "blob") {
            // Multilayer (MLCC) style: a tall, dipped rounded body.
            const bw = r * 1.5;
            const bh = r * 1.95;
            const bx = cx - bw / 2;
            const by = topMargin;
            const k = bw * 0.44;
            bodyBottom = by + bh;
            bodyPath =
                `M ${bx} ${by + k} Q ${bx} ${by} ${bx + k} ${by} L ${bx + bw - k} ${by} ` +
                `Q ${bx + bw} ${by} ${bx + bw} ${by + k} L ${bx + bw} ${bodyBottom - k} ` +
                `Q ${bx + bw} ${bodyBottom} ${bx + bw - k} ${bodyBottom} L ${bx + k} ${bodyBottom} ` +
                `Q ${bx} ${bodyBottom} ${bx} ${bodyBottom - k} Z`;
            gloss = {cx, cy: by + bh * 0.26, rx: bw * 0.4, ry: bh * 0.22};
            spec = {cx: cx - bw * 0.22, cy: by + bh * 0.16, rx: 13, ry: 7};
            botShadow = {cx, cy: bodyBottom - bh * 0.1, rx: bw * 0.42, ry: bh * 0.12};
            topDip = {cx, cy: by + 3, rx: bw * 0.18, ry: 7};
            textCy = by + bh * 0.42;
        } else {
            // Radial disc: a near-full circle whose bottom tapers *inward* to the two lead exits,
            // with a small dip between the leads. The shoulder is always kept wider than the lead
            // roots so the taper never bulges out past the circle.
            const shoulderHalf = Math.min(r - 3, Math.max(0.6 * r, pitchPx / 2 + 14));
            const shoulderY = cy + Math.sqrt(Math.max(0, r * r - shoulderHalf * shoulderHalf));
            const rt = {x: cx + shoulderHalf, y: shoulderY};
            const lt = {x: cx - shoulderHalf, y: shoulderY};
            const rRoot = Math.min(leadX2 + 4, rt.x - 2);
            const lRoot = Math.max(leadX1 - 4, lt.x + 2);
            bodyBottom = cy + r + Math.max(6, r * 0.1);
            const notchY = bodyBottom - Math.max(7, r * 0.13);
            const drop = bodyBottom - shoulderY;
            bodyPath =
                `M ${lt.x} ${lt.y} ` +
                `A ${r} ${r} 0 1 1 ${rt.x} ${rt.y} ` +
                `C ${rt.x} ${shoulderY + drop * 0.5} ${rRoot + 4} ${bodyBottom - drop * 0.28} ${rRoot} ${bodyBottom} ` +
                `Q ${cx + (rRoot - cx) * 0.5} ${bodyBottom} ${cx} ${notchY} ` +
                `Q ${cx - (rRoot - cx) * 0.5} ${bodyBottom} ${lRoot} ${bodyBottom} ` +
                `C ${lRoot - 4} ${bodyBottom - drop * 0.28} ${lt.x} ${shoulderY + drop * 0.5} ${lt.x} ${lt.y} Z`;
            gloss = {cx, cy: cy - r * 0.28, rx: r * 0.72, ry: r * 0.34};
            spec = {cx: cx - r * 0.26, cy: cy - r * 0.44, rx: r * 0.16, ry: r * 0.09};
            botShadow = {cx, cy: bodyBottom - r * 0.14, rx: r * 0.6, ry: r * 0.2};
            topDip = {cx, cy: cy - r + 5, rx: r * 0.14, ry: 6};
            textCy = cy - r * 0.04;
        }

        // Marking text (capacitance code) with an optional printed voltage line below it.
        const baseFont = Math.round(Math.max(20, Math.min(40, r * 0.52)));
        const codeFont = marking.length > 4 ? Math.round(baseFont * 0.8) : baseFont;
        const codeY = voltage ? textCy - codeFont * 0.42 : textCy;
        const voltFont = Math.round(codeFont * 0.55);
        const voltageSvg = voltage
            ? `<text x="${cx}" y="${textCy + codeFont * 0.55}" text-anchor="middle" dominant-baseline="central"
                     font-family="monospace" font-weight="bold" font-size="${voltFont}" fill="${textColor}"
                     style="paint-order:stroke" stroke="${shadow}" stroke-width="0.5">${voltage}V</text>`
            : "";

        // Leads.
        const leadTop = bodyBottom - 6;
        const leadEnd = bodyBottom + leadExtra;
        const leadW = 3.4;
        const leads =
            `<rect x="${leadX1 - leadW / 2}" y="${leadTop}" width="${leadW}" height="${leadEnd - leadTop}" rx="${leadW / 2}" fill="url(#${uid}lead)"/>`
            + `<rect x="${leadX2 - leadW / 2}" y="${leadTop}" width="${leadW}" height="${leadEnd - leadTop}" rx="${leadW / 2}" fill="url(#${uid}lead)"/>`;

        const H = Math.ceil(leadEnd + 22);
        const pitchLabel = pitchIn ? `pitch ${this.formatMm(parseFloat(pitch))} (${pitchIn})` : `pitch ${this.formatMm(parseFloat(pitch))}`;
        const callouts =
            this.dimH(cx - r, cx + r, topMargin - 12, `⌀ ${this.formatMm(diam)}`)
            + this.dimH(leadX1, leadX2, H - 10, pitchLabel);

        //The body shows the printed code (and voltage); add the decoded capacitance + tolerance below.
        const capTol = this.capToleranceText();
        const valueLabel = this.capPf > 0 ? this.formatFarads(this.capPf) + (capTol ? ` · ${capTol}` : "") : "";
        const totalH = H + (valueLabel ? 24 : 0);
        const valueCaption = valueLabel
            ? `<text x="${cx}" y="${totalH - 8}" text-anchor="middle" font-family="monospace" font-size="15" font-weight="700" fill="#3a4149">${valueLabel}</text>`
            : "";

        const svg = `
        <svg viewBox="0 0 ${W} ${totalH}" xmlns="http://www.w3.org/2000/svg" style="max-width: 250px; width: 100%; height: auto;">
            <defs>
                ${this.leadGradient(uid)}
                ${this.blurFilter(uid)}
                <clipPath id="${uid}clip"><path d="${bodyPath}"/></clipPath>
                <linearGradient id="${uid}amb" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stop-color="#ffffff" stop-opacity="0.2"/>
                    <stop offset="0.42" stop-color="#ffffff" stop-opacity="0"/>
                    <stop offset="1" stop-color="#000000" stop-opacity="0.16"/>
                </linearGradient>
            </defs>
            <g>
                ${leads}
                <path d="${bodyPath}" fill="${fill}"/>
                <g clip-path="url(#${uid}clip)">
                    <rect x="0" y="0" width="${W}" height="${H}" fill="url(#${uid}amb)"/>
                    <ellipse cx="${gloss.cx}" cy="${gloss.cy}" rx="${gloss.rx}" ry="${gloss.ry}" fill="#ffffff" opacity="0.26" filter="url(#${uid}blur)"/>
                    <ellipse cx="${spec.cx}" cy="${spec.cy}" rx="${spec.rx}" ry="${spec.ry}" fill="#ffffff" opacity="0.6" filter="url(#${uid}blur)"/>
                    <ellipse cx="${botShadow.cx}" cy="${botShadow.cy}" rx="${botShadow.rx}" ry="${botShadow.ry}" fill="#000000" opacity="0.12" filter="url(#${uid}blur)"/>
                    <ellipse cx="${topDip.cx}" cy="${topDip.cy}" rx="${topDip.rx}" ry="${topDip.ry}" fill="#000000" opacity="0.18" filter="url(#${uid}blur)"/>
                </g>
                <path d="${bodyPath}" fill="none" stroke="#00000055" stroke-width="1.2"/>
                <text x="${cx}" y="${codeY}" text-anchor="middle" dominant-baseline="central"
                      font-family="monospace" font-weight="bold" font-size="${codeFont}"
                      fill="${textColor}" style="paint-order:stroke" stroke="${shadow}" stroke-width="0.6">${this.escapeXml(marking)}</text>
                ${voltageSvg}
            </g>
            ${callouts}
            ${valueCaption}
        </svg>`;
        target.innerHTML = svg;
        this.updateCapSpec();
    }

    capShapeValue() {
        const v = this.hasCapShapeTarget ? this.capShapeTarget.value : "disc";
        return v === "blob" ? "blob" : "disc";
    }

    capLeadValue() {
        const v = this.hasCapLeadTarget ? this.capLeadTarget.value : "medium";
        return Object.prototype.hasOwnProperty.call(CAP_LEAD_LENGTHS, v) ? v : "medium";
    }

    capPitchValue() {
        const v = this.hasCapPitchTarget ? this.capPitchTarget.value : "5.08";
        return CAP_PITCHES[v] !== undefined ? v : "5.08";
    }

    capDiameterValue() {
        const v = this.hasCapDiameterTarget ? parseFloat(this.capDiameterTarget.value) : NaN;
        return Number.isFinite(v) && v > 0 ? v : 5;
    }

    capVoltageValue() {
        const v = this.hasCapVoltageTarget ? this.capVoltageTarget.value.trim() : "";
        return /^\d+(\.\d+)?$/.test(v) ? v : "";
    }

    updateCapSpec() {
        if (!this.hasCapSpecTarget) {
            return;
        }
        const pitch = this.capPitchValue();
        const pitchIn = CAP_PITCHES[pitch];
        let spec = `⌀ ${this.formatMm(this.capDiameterValue())} · pitch ${this.formatMm(parseFloat(pitch))}${pitchIn ? ` (${pitchIn})` : ""}`;
        const voltage = this.capVoltageValue();
        if (voltage) {
            spec += ` · ${voltage} V`;
        }
        this.capSpecTarget.textContent = spec;
    }

    /** Re-renders the capacitor picture when the body color changes. */
    updateCapacitorColor() {
        this.redrawCap();
    }

    /** Updates the spec line and re-renders the capacitor picture. */
    updateCapDimensions() {
        this.updateCapSpec();
        this.redrawCap();
    }

    applyCapBodyColor(event) {
        if (this.hasCapBodyColorTarget) {
            this.capBodyColorTarget.value = event.currentTarget.dataset.color;
        }
        this.redrawCap();
    }

    /** Unique id prefix per drawn SVG, so gradient/filter ids never collide. */
    svgId() {
        this.svgSeq = (this.svgSeq || 0) + 1;
        return `vc${this.svgSeq}_`;
    }

    /** Vertical metallic gradient used for component leads. */
    leadGradient(uid) {
        return `<linearGradient id="${uid}lead" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#9aa0a6"/>
            <stop offset="0.45" stop-color="#f4f6f8"/>
            <stop offset="0.55" stop-color="#e7eaed"/>
            <stop offset="1" stop-color="#6f747a"/>
        </linearGradient>`;
    }

    /** Vertical metallic gradient for SMD terminations. */
    metalGradient(uid) {
        return `<linearGradient id="${uid}metal" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#eef1f4"/>
            <stop offset="0.5" stop-color="#c2c7cd"/>
            <stop offset="1" stop-color="#9098a0"/>
        </linearGradient>`;
    }

    /** Top-light / bottom-dark overlay that turns a flat shape into a cylinder. */
    cylinderGradient(uid) {
        return `<linearGradient id="${uid}cyl" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#ffffff" stop-opacity="0.55"/>
            <stop offset="0.16" stop-color="#ffffff" stop-opacity="0.16"/>
            <stop offset="0.46" stop-color="#ffffff" stop-opacity="0"/>
            <stop offset="0.72" stop-color="#000000" stop-opacity="0.16"/>
            <stop offset="1" stop-color="#000000" stop-opacity="0.42"/>
        </linearGradient>`;
    }

    /** Softer top-gloss overlay for caps and SMD bodies. */
    glossGradient(uid) {
        return `<linearGradient id="${uid}gloss" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#ffffff" stop-opacity="0.4"/>
            <stop offset="0.4" stop-color="#ffffff" stop-opacity="0.05"/>
            <stop offset="0.62" stop-color="#000000" stop-opacity="0"/>
            <stop offset="1" stop-color="#000000" stop-opacity="0.32"/>
        </linearGradient>`;
    }

    /** Radial highlight used as a specular reflection on the cap body. */
    specularGradient(uid) {
        return `<radialGradient id="${uid}spec" cx="0.5" cy="0.5" r="0.5">
            <stop offset="0" stop-color="#ffffff" stop-opacity="0.55"/>
            <stop offset="1" stop-color="#ffffff" stop-opacity="0"/>
        </radialGradient>`;
    }

    /** Horizontal vignette that darkens the rounded ends of a cylinder. */
    endVignetteGradient(uid) {
        return `<linearGradient id="${uid}vig" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#000000" stop-opacity="0.38"/>
            <stop offset="0.1" stop-color="#000000" stop-opacity="0.06"/>
            <stop offset="0.16" stop-color="#000000" stop-opacity="0"/>
            <stop offset="0.84" stop-color="#000000" stop-opacity="0"/>
            <stop offset="0.9" stop-color="#000000" stop-opacity="0.06"/>
            <stop offset="1" stop-color="#000000" stop-opacity="0.38"/>
        </linearGradient>`;
    }

    /** Soft gaussian blur, used for specular streaks and ground shadows. */
    blurFilter(uid) {
        return `<filter id="${uid}blur" x="-50%" y="-50%" width="200%" height="200%">
            <feGaussianBlur stdDeviation="3"/>
        </filter>`;
    }

    /** Soft, slightly offset drop shadow filter. */
    /**
     * A soft, slightly offset drop shadow, built from primitives that survive the server-side SVG
     * sanitizer applied when the picture is attached to a part. The shorthand <feDropShadow> element
     * is NOT on the sanitizer's filter-primitive allow-list and gets stripped on save, leaving an
     * empty <filter> — which the SVG spec defines as fully transparent, silently hiding whatever
     * element referenced it (only visible once the attachment is viewed as a real, saved image
     * rather than in this live preview). This is the equivalent built from feGaussianBlur/feOffset/
     * feFlood/feComposite/feMerge, all of which are allow-listed and pass through unchanged.
     */
    shadowFilter(uid) {
        return `<filter id="${uid}shadow" x="-15%" y="-20%" width="130%" height="160%">
            <feGaussianBlur in="SourceAlpha" stdDeviation="5" result="${uid}blurShadow"/>
            <feOffset in="${uid}blurShadow" dx="0" dy="4" result="${uid}offsetShadow"/>
            <feFlood flood-color="#000000" flood-opacity="0.28" result="${uid}floodShadow"/>
            <feComposite in="${uid}floodShadow" in2="${uid}offsetShadow" operator="in" result="${uid}coloredShadow"/>
            <feMerge>
                <feMergeNode in="${uid}coloredShadow"/>
                <feMergeNode in="SourceGraphic"/>
            </feMerge>
        </filter>`;
    }

    /** Horizontal dimension line with end ticks, arrows and a centered label above. */
    dimH(x1, x2, y, label) {
        const t = 4;
        return `<g stroke="${DIM_COLOR}" stroke-width="1" fill="${DIM_COLOR}" font-size="11" font-family="system-ui, Arial, sans-serif">
            <line x1="${x1}" y1="${y - t}" x2="${x1}" y2="${y + t}"/>
            <line x1="${x2}" y1="${y - t}" x2="${x2}" y2="${y + t}"/>
            <line x1="${x1}" y1="${y}" x2="${x2}" y2="${y}"/>
            <polygon stroke="none" points="${x1},${y} ${x1 + 6},${y - 3} ${x1 + 6},${y + 3}"/>
            <polygon stroke="none" points="${x2},${y} ${x2 - 6},${y - 3} ${x2 - 6},${y + 3}"/>
            <text x="${(x1 + x2) / 2}" y="${y - 5}" text-anchor="middle" stroke="none">${label}</text>
        </g>`;
    }

    /** Vertical dimension line (label centered above) with optional extension lines. */
    dimV(y1, y2, x, label, extFromX = null) {
        const t = 4;
        const ext = extFromX === null ? "" :
            `<line x1="${extFromX}" y1="${y1}" x2="${x + t}" y2="${y1}" stroke-dasharray="2 2"/>
             <line x1="${extFromX}" y1="${y2}" x2="${x + t}" y2="${y2}" stroke-dasharray="2 2"/>`;
        return `<g stroke="${DIM_COLOR}" stroke-width="1" fill="${DIM_COLOR}" font-size="11" font-family="system-ui, Arial, sans-serif">
            ${ext}
            <line x1="${x - t}" y1="${y1}" x2="${x + t}" y2="${y1}"/>
            <line x1="${x - t}" y1="${y2}" x2="${x + t}" y2="${y2}"/>
            <line x1="${x}" y1="${y1}" x2="${x}" y2="${y2}"/>
            <polygon stroke="none" points="${x},${y1} ${x - 3},${y1 + 6} ${x + 3},${y1 + 6}"/>
            <polygon stroke="none" points="${x},${y2} ${x - 3},${y2 - 6} ${x + 3},${y2 - 6}"/>
            <text x="${x}" y="${y1 - 6}" text-anchor="middle" stroke="none">${label}</text>
        </g>`;
    }

    /** Formats a millimeter value without trailing zeros. */
    formatMm(mm) {
        return `${this.trimNumber(mm)} mm`;
    }

    /** Formats a power rating in watts, preferring the fractional label. */
    formatPower(watts) {
        const fractions = {0.125: "1/8 W", 0.25: "1/4 W", 0.33: "1/3 W", 0.5: "1/2 W"};
        return fractions[watts] ?? `${this.trimNumber(watts)} W`;
    }

    /** Returns the value of a color input, falling back to a default. */
    bodyColor(target, fallback) {
        return target && target.value ? target.value : fallback;
    }

    /** Picks black or white text for readable contrast on the given hex color. */
    contrastColor(hex) {
        const c = hex.replace("#", "");
        if (c.length < 6) {
            return "#1a1100";
        }
        const r = parseInt(c.substring(0, 2), 16);
        const g = parseInt(c.substring(2, 4), 16);
        const b = parseInt(c.substring(4, 6), 16);
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
        return luminance > 0.6 ? "#1a1100" : "#f5f5f5";
    }

    /**
     * Escapes a string for safe interpolation into the SVG markup we build with template strings and
     * assign via innerHTML. The live preview is NOT server-sanitized, so anything derived from part
     * data (e.g. a diode marking) must be escaped here as defence-in-depth against markup injection.
     */
    escapeXml(value) {
        return String(value).replace(/[&<>"']/g, (c) =>
            ({"&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"})[c]);
    }

    /** Returns hex only if it is a valid #rgb/#rrggbb(aa) colour, else the fallback — so a colour value can't break out of an attribute. */
    safeColor(hex, fallback = "#000000") {
        return /^#[0-9a-fA-F]{3,8}$/.test(String(hex)) ? String(hex) : fallback;
    }

    /*
     * ---------------------------------------------------------------
     *  SMD resistor code
     * ---------------------------------------------------------------
     */

    /** Recomputes all linked SMD fields (and the picture) from whichever was edited. */
    syncSmd(event) {
        const field = event.currentTarget.dataset.field;
        const raw = event.currentTarget.value;
        const ohms = field === "value" ? this.parseValue(raw, "R") : this.smdCodeToOhms(raw);
        if (ohms === null || !(ohms > 0)) {
            event.currentTarget.classList.add("is-invalid");
            this.smdOhms = null;
            if (this.hasSmdResultTarget) {
                this.smdResultTarget.textContent = raw.trim() === "" ? "" : trans("tools.value_calc.invalid_input");
            }
            this.smdSvgTarget.innerHTML = "";
            return;
        }
        event.currentTarget.classList.remove("is-invalid");
        this.smdOhms = ohms;
        this.setSmdFields(ohms, field);
        this.redrawSmd();
    }

    /** Fills the value / 3-digit / 4-digit / EIA-96 fields (skips the field being edited). */
    setSmdFields(ohms, except) {
        const fields = {
            value: () => this.formatOhms(ohms),
            code3: () => this.ohmsToSmdCode(ohms) ?? "",
            code4: () => this.ohmsTo4Digit(ohms) ?? "",
            eia96: () => this.ohmsToEia96(ohms) ?? "—",
        };
        const targets = {
            value: this.hasSmdValueInputTarget ? this.smdValueInputTarget : null,
            code3: this.hasSmdCode3Target ? this.smdCode3Target : null,
            code4: this.hasSmdCode4Target ? this.smdCode4Target : null,
            eia96: this.hasSmdEia96Target ? this.smdEia96Target : null,
        };
        for (const key of Object.keys(fields)) {
            const t = targets[key];
            if (!t) {
                continue;
            }
            if (key !== except) {
                t.value = fields[key]();
            }
            t.classList.remove("is-invalid");
        }
    }

    /** Draws the SMD chip using the marking currently selected as "printed on the part". */
    redrawSmd() {
        if (this.smdOhms === null || this.smdOhms === undefined || !(this.smdOhms > 0)) {
            return;
        }
        const codes = {
            code3: this.ohmsToSmdCode(this.smdOhms),
            code4: this.ohmsTo4Digit(this.smdOhms),
            eia96: this.ohmsToEia96(this.smdOhms),
        };
        const mark = this.smdMarking || "code3";
        const marking = codes[mark] || codes.code3 || this.formatOhms(this.smdOhms);
        if (this.hasSmdResultTarget) {
            this.smdResultTarget.textContent = this.formatOhms(this.smdOhms);
        }
        this.drawSmd(this.smdSvgTarget, marking, {tolerance: this.smdTolerance, voltage: this.smdVoltage});
        this.highlightSmdMarking();
    }

    /** Chooses which code is printed on the drawn chip. */
    pickSmdMarking(event) {
        this.smdMarking = event.currentTarget.dataset.mark;
        this.redrawSmd();
    }

    /** Outlines the field whose code is currently drawn on the chip. */
    highlightSmdMarking() {
        const map = {
            code3: this.hasSmdCode3Target ? this.smdCode3Target : null,
            code4: this.hasSmdCode4Target ? this.smdCode4Target : null,
            eia96: this.hasSmdEia96Target ? this.smdEia96Target : null,
        };
        const active = this.smdMarking || "code3";
        for (const [key, t] of Object.entries(map)) {
            if (t) {
                t.classList.toggle("border-primary", key === active);
                t.classList.toggle("border-2", key === active);
            }
        }
    }

    /** Parses any SMD marking (R-notation, EIA-96, 3-digit, 4-digit) to ohms, or null. */
    smdCodeToOhms(raw) {
        const code = (raw || "").trim().toUpperCase();
        if (code === "") {
            return null;
        }
        if (code.includes("R") && /^\d*R\d*$/.test(code)) {
            const v = parseFloat(code.replace("R", "."));
            return Number.isNaN(v) ? null : v;
        }
        if (/^\d{2}[A-Z]$/.test(code)) {
            const n = parseInt(code.substring(0, 2), 10);
            const letter = code.charAt(2);
            if (n >= 1 && n <= 96 && EIA96_MULTIPLIERS[letter] !== undefined) {
                return EIA96_VALUES[n - 1] * EIA96_MULTIPLIERS[letter];
            }
            return null;
        }
        if (/^\d{3}$/.test(code)) {
            return parseInt(code.substring(0, 2), 10) * Math.pow(10, parseInt(code.charAt(2), 10));
        }
        if (/^\d{4}$/.test(code)) {
            return parseInt(code.substring(0, 3), 10) * Math.pow(10, parseInt(code.charAt(3), 10));
        }
        return null;
    }

    /** ohms -> 4-digit precision code (3 significant figures), R-notation below 100 Ω. */
    ohmsTo4Digit(ohms) {
        if (!(ohms > 0)) {
            return null;
        }
        if (ohms < 100) {
            let s = parseFloat(ohms.toPrecision(3)).toString();
            if (!s.includes(".")) {
                s += ".0";
            }
            return s.startsWith("0.") ? "R" + s.slice(2) : s.replace(".", "R");
        }
        let exp = Math.floor(Math.log10(ohms)) - 2;
        let significant = Math.round(ohms / Math.pow(10, exp));
        if (significant >= 1000) {
            significant = Math.round(significant / 10);
            exp += 1;
        }
        if (exp < 0 || exp > 9) {
            return null;
        }
        return significant.toString().padStart(3, "0") + exp.toString();
    }

    /** ohms -> EIA-96 code (value code + multiplier letter) for E96 values, else null. */
    ohmsToEia96(ohms) {
        if (!(ohms > 0)) {
            return null;
        }
        const order = ["A", "B", "C", "D", "E", "F", "X", "S", "Y", "R", "Z"];
        for (const letter of order) {
            const base = ohms / EIA96_MULTIPLIERS[letter];
            const idx = EIA96_VALUES.findIndex((v) => Math.abs(v - base) < 0.5);
            if (idx >= 0) {
                return String(idx + 1).padStart(2, "0") + letter;
            }
        }
        return null;
    }

    /** Re-renders the SMD chip when the package changes. */
    updateSmd() {
        this.redrawSmd();
    }

    /** Re-renders the SMD chip when the body color changes. */
    updateSmdColor() {
        this.redrawSmd();
    }

    applySmdBodyColor(event) {
        if (this.hasSmdBodyColorTarget) {
            this.smdBodyColorTarget.value = event.currentTarget.dataset.color;
        }
        this.redrawSmd();
    }

    /**
     * Converts a resistance in ohms into the printed SMD marking: R-notation below
     * 10 Ω (4.7 -> 4R7, 0.47 -> R47) and the 3-digit EIA code from 10 Ω upwards.
     * Returns null when the value is out of the representable range.
     */
    ohmsToSmdCode(ohms) {
        if (!(ohms > 0)) {
            return null;
        }
        if (ohms < 10) {
            let s = parseFloat(ohms.toFixed(2)).toString();
            if (!s.includes(".")) {
                s += ".0";
            }
            return s.startsWith("0.") ? "R" + s.slice(2) : s.replace(".", "R");
        }
        // Two significant figures + power-of-ten multiplier digit
        let exp = Math.floor(Math.log10(ohms)) - 1;
        let significant = Math.round(ohms / Math.pow(10, exp));
        if (significant >= 100) {
            significant = Math.round(significant / 10);
            exp += 1;
        }
        if (exp < 0 || exp > 7) {
            return null;
        }
        return significant.toString().padStart(2, "0") + exp.toString();
    }

    /** Draws a 3D-shaded SMD chip resistor with marking and dimension callouts. */
    drawSmd(target, marking, spec = {}) {
        const uid = this.svgId();
        const w = 300;
        const pkgKey = this.smdPackageValue();
        const pkg = SMD_PACKAGES[pkgKey];

        // Body proportions follow the package: the length maps to a modest on-screen width
        // (kept readable rather than true 1:1 scale) and the L:W ratio sets the height, so
        // a 2512 looks noticeably larger than a 0402 and a 1210 looks squarer.
        const bodyW = Math.round(120 + 90 * (pkg.l - 0.6) / (6.3 - 0.6));
        const aspect = pkg.l / pkg.w;
        const bodyH = Math.max(48, Math.min(122, Math.round(bodyW / aspect)));
        const capW = Math.max(14, Math.round(bodyW * 0.13));

        const cx = w / 2;
        const bodyX = Math.round(cx - bodyW / 2);
        const bodyY = Math.round(78 - bodyH / 2);
        const bodyBottom = bodyY + bodyH;
        const cy = bodyY + bodyH / 2;

        const innerX = bodyX + capW;
        const innerW = bodyW - 2 * capW;

        // Fit the marking inside the ceramic window (bounded by both width and height).
        const fontSize = Math.max(14, Math.min(
            34,
            Math.round(bodyH * 0.5),
            Math.round(innerW * 1.6 / Math.max(3, marking.length))
        ));

        const fill = this.safeColor(this.bodyColor(this.hasSmdBodyColorTarget ? this.smdBodyColorTarget : null, "#262626"), "#262626");
        const textColor = this.contrastColor(fill);

        const callouts =
            this.dimH(bodyX, bodyX + bodyW, bodyBottom + 18, `L ${this.formatMm(pkg.l)}`)
            + this.dimV(bodyY, bodyBottom, bodyX + bodyW + 16, `W ${this.formatMm(pkg.w)}`, bodyX + bodyW);

        //The chip itself shows the printed code; print the decoded value (+ tolerance) as a caption below.
        const valueLabel = this.smdOhms > 0 ? this.formatOhms(this.smdOhms) + this.specSuffix(spec) : "";
        const h = bodyBottom + 54;
        const valueCaption = valueLabel
            ? `<text x="${cx}" y="${h - 12}" text-anchor="middle" font-family="monospace" font-size="16" font-weight="700" fill="#3a4149">${valueLabel}</text>`
            : "";

        const svg = `
        <svg viewBox="0 0 ${w} ${h}" xmlns="http://www.w3.org/2000/svg" style="max-width: 300px; width: 100%; height: auto;">
            <defs>
                ${this.metalGradient(uid)}
                ${this.glossGradient(uid)}
                ${this.blurFilter(uid)}
                <clipPath id="${uid}clip"><rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" rx="8" ry="8"/></clipPath>
                ${this.shadowFilter(uid)}
            </defs>
            <g>
                <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" rx="8" ry="8" fill="url(#${uid}metal)" stroke="#00000055" stroke-width="1"/>
                <g clip-path="url(#${uid}clip)">
                    <rect x="${innerX}" y="${bodyY}" width="${innerW}" height="${bodyH}" fill="${fill}"/>
                    <rect x="${innerX}" y="${bodyY}" width="${innerW}" height="${bodyH}" fill="url(#${uid}gloss)"/>
                    <rect x="${innerX}" y="${bodyY + 2}" width="${innerW}" height="2.5" fill="#ffffff" opacity="0.22"/>
                    <rect x="${innerX - 2}" y="${bodyY}" width="3" height="${bodyH}" fill="#000000" opacity="0.28"/>
                    <rect x="${innerX + innerW - 1}" y="${bodyY}" width="3" height="${bodyH}" fill="#000000" opacity="0.28"/>
                </g>
                <text x="${cx}" y="${cy}" text-anchor="middle" dominant-baseline="central"
                      font-family="monospace" font-weight="bold" font-size="${fontSize}" fill="${textColor}">${this.escapeXml(marking)}</text>
            </g>
            ${callouts}
            ${valueCaption}
        </svg>`;
        target.innerHTML = svg;

        if (this.hasSmdSpecTarget) {
            this.smdSpecTarget.textContent =
                `${pkgKey} (${pkg.metric}) · ${this.formatMm(pkg.l)} × ${this.formatMm(pkg.w)} · ${this.formatPower(pkg.power)}`;
        }
    }

    smdPackageValue() {
        const v = this.hasSmdPackageTarget ? this.smdPackageTarget.value : "0805";
        return SMD_PACKAGES[v] ? v : "0805";
    }

    /*
     * ---------------------------------------------------------------
     *  SMD capacitor tab (interactive) — an MLCC chip. These are (almost) always unmarked, so there
     *  is nothing to decode: you enter the value + package and it draws the picture to attach.
     * ---------------------------------------------------------------
     */

    /** Reads the SMD-capacitor value input and redraws the MLCC chip. */
    syncSmdCap() {
        if (!this.hasSmdCapSvgTarget) {
            return;
        }
        const raw = this.hasSmdCapValueInputTarget ? this.smdCapValueInputTarget.value : "100n";
        const farads = this.parseValue(raw, "F");
        if (farads === null || !(farads > 0)) {
            if (this.hasSmdCapValueInputTarget) {
                this.smdCapValueInputTarget.classList.toggle("is-invalid", (raw || "").trim() !== "");
            }
            this.smdCapSvgTarget.innerHTML = "";
            return;
        }
        if (this.hasSmdCapValueInputTarget) {
            this.smdCapValueInputTarget.classList.remove("is-invalid");
        }
        this.smdCapPf = farads * 1e12; //formatFarads() works in picofarads
        this.drawSmdCapacitor(this.smdCapSvgTarget, {
            package: this.smdCapPackageValue(),
            bodyColor: this.hasSmdCapBodyColorTarget ? this.smdCapBodyColorTarget.value : null,
            voltage: this.hasSmdCapVoltageTarget ? this.smdCapVoltageTarget.value : null,
            tolerance: this.hasSmdCapToleranceTarget ? this.smdCapToleranceTarget.value : null,
            specEl: this.hasSmdCapSpecTarget ? this.smdCapSpecTarget : null,
        });
    }

    smdCapPackageValue() {
        const v = this.hasSmdCapPackageTarget ? this.smdCapPackageTarget.value : "0805";
        return SMD_PACKAGES[v] ? v : "0805";
    }

    applySmdCapBodyColor(event) {
        if (this.hasSmdCapBodyColorTarget) {
            this.smdCapBodyColorTarget.value = event.currentTarget.dataset.color;
        }
        this.syncSmdCap();
    }

    /**
     * Draws a surface-mount MLCC capacitor: a tan ceramic block with wide metal end terminations and
     * (as on real MLCCs) no printed marking — the decoded value is shown as a caption below instead.
     */
    drawSmdCapacitor(target, options = {}) {
        const uid = this.svgId();
        const w = 300;
        const pkgKey = SMD_PACKAGES[options.package] ? options.package : "0805";
        const pkg = SMD_PACKAGES[pkgKey];

        const bodyW = Math.round(120 + 90 * (pkg.l - 0.6) / (6.3 - 0.6));
        const aspect = pkg.l / pkg.w;
        const bodyH = Math.max(48, Math.min(122, Math.round(bodyW / aspect)));
        //MLCC end terminations are noticeably wider than a chip resistor's.
        const termW = Math.max(20, Math.round(bodyW * 0.20));

        const cx = w / 2;
        const bodyX = Math.round(cx - bodyW / 2);
        const bodyY = Math.round(78 - bodyH / 2);
        const bodyBottom = bodyY + bodyH;
        const innerX = bodyX + termW;
        const innerW = bodyW - 2 * termW;

        const fill = this.safeColor(options.bodyColor, "#c8a37a");

        const callouts =
            this.dimH(bodyX, bodyX + bodyW, bodyBottom + 18, `L ${this.formatMm(pkg.l)}`)
            + this.dimV(bodyY, bodyBottom, bodyX + bodyW + 16, `W ${this.formatMm(pkg.w)}`, bodyX + bodyW);

        const valueLabel = this.smdCapPf > 0 ? this.formatFarads(this.smdCapPf) + this.specSuffix(options) : "";
        const h = bodyBottom + 54;
        const valueCaption = valueLabel
            ? `<text x="${cx}" y="${h - 12}" text-anchor="middle" font-family="monospace" font-size="16" font-weight="700" fill="#3a4149">${valueLabel}</text>`
            : "";

        target.innerHTML = `
        <svg viewBox="0 0 ${w} ${h}" xmlns="http://www.w3.org/2000/svg" style="max-width: 300px; width: 100%; height: auto;">
            <defs>
                ${this.metalGradient(uid)}
                ${this.glossGradient(uid)}
                ${this.blurFilter(uid)}
                <clipPath id="${uid}clip"><rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" rx="10" ry="10"/></clipPath>
                ${this.shadowFilter(uid)}
            </defs>
            <g>
                <rect x="${bodyX}" y="${bodyY}" width="${bodyW}" height="${bodyH}" rx="10" ry="10" fill="url(#${uid}metal)" stroke="#00000055" stroke-width="1" filter="url(#${uid}shadow)"/>
                <g clip-path="url(#${uid}clip)">
                    <rect x="${innerX}" y="${bodyY}" width="${innerW}" height="${bodyH}" fill="${fill}"/>
                    <rect x="${innerX}" y="${bodyY}" width="${innerW}" height="${bodyH}" fill="url(#${uid}gloss)"/>
                    <rect x="${innerX}" y="${bodyY + 2}" width="${innerW}" height="3" fill="#ffffff" opacity="0.22"/>
                    <rect x="${innerX - 2}" y="${bodyY}" width="3" height="${bodyH}" fill="#000000" opacity="0.25"/>
                    <rect x="${innerX + innerW - 1}" y="${bodyY}" width="3" height="${bodyH}" fill="#000000" opacity="0.25"/>
                </g>
            </g>
            ${callouts}
            ${valueCaption}
        </svg>`;

        if (options.specEl) {
            options.specEl.textContent =
                `${pkgKey} (${pkg.metric}) · ${this.formatMm(pkg.l)} × ${this.formatMm(pkg.w)}${valueLabel ? ` · ${valueLabel}` : ""}`;
        }
    }

    /*
     * ---------------------------------------------------------------
     *  SMD inductor tab (interactive)
     * ---------------------------------------------------------------
     */

    /**
     * Recomputes the linked SMD-inductor value/code fields (and the picture) from whichever was
     * edited — typing a value fills the code, and (like the SMD resistor tab) typing a code fills
     * the value. Called with no event for programmatic redraws (package/colour change, init).
     */
    syncSmdInductor(event) {
        if (!this.hasSmdIndSvgTarget) {
            return;
        }
        const field = event && event.currentTarget && event.currentTarget.dataset ? event.currentTarget.dataset.field : null;
        const activeInput = field === "code" && this.hasSmdIndCodeTarget ? this.smdIndCodeTarget
            : (this.hasSmdIndValueInputTarget ? this.smdIndValueInputTarget : null);

        let henries;
        if (field === "code") {
            henries = this.inductorCodeToHenries(this.hasSmdIndCodeTarget ? this.smdIndCodeTarget.value : "");
        } else {
            const raw = (this.hasSmdIndValueInputTarget ? this.smdIndValueInputTarget.value : "100u").trim();
            //Accept "100µH", "10mH", "4.7uH"; a bare number (no prefix) reads as µH, matching the THT inductor tab.
            const m = raw.match(/^([\d.]+)\s*(p|n|u|µ|m)?\s*h?$/i);
            if (m) {
                const num = parseFloat(m[1]);
                const factors = {p: 1e-12, n: 1e-9, u: 1e-6, "µ": 1e-6, m: 1e-3};
                henries = m[2] ? num * factors[m[2].toLowerCase()] : num * 1e-6;
            } else {
                henries = null;
            }
        }

        if (henries === null || !(henries > 0)) {
            if (activeInput) {
                activeInput.classList.toggle("is-invalid", activeInput.value.trim() !== "");
            }
            this.smdIndSvgTarget.innerHTML = "";
            return;
        }
        if (activeInput) {
            activeInput.classList.remove("is-invalid");
        }

        const marking = this.henriesToInductorCode(henries / 1e-6);
        if (field !== "code" && this.hasSmdIndCodeTarget) {
            this.smdIndCodeTarget.value = marking;
        }
        if (field !== "value" && this.hasSmdIndValueInputTarget) {
            this.smdIndValueInputTarget.value = this.formatHenries(henries);
        }
        this.drawSmdInductor(this.smdIndSvgTarget, marking, henries, {
            package: this.smdIndPackageValue(),
            bodyColor: this.hasSmdIndBodyColorTarget ? this.smdIndBodyColorTarget.value : null,
            specEl: this.hasSmdIndSpecTarget ? this.smdIndSpecTarget : null,
        });
    }

    /**
     * Parses an SMD-inductor marking back to henries: R-notation (4R7 = 4.7 µH) or the 3-digit EIA
     * code (101 = 100 µH), the same two forms {@see henriesToInductorCode} prints. Returns null for
     * anything else (e.g. a 4-digit or EIA-96 code, which this chip type isn't drawn with).
     */
    inductorCodeToHenries(raw) {
        const code = (raw || "").trim().toUpperCase();
        if (code === "") {
            return null;
        }
        if (code.includes("R") && /^\d*R\d*$/.test(code)) {
            const v = parseFloat(code.replace("R", "."));
            return Number.isNaN(v) ? null : v * 1e-6;
        }
        if (/^\d{3}$/.test(code)) {
            const uH = parseInt(code.substring(0, 2), 10) * Math.pow(10, parseInt(code.charAt(2), 10));
            return uH * 1e-6;
        }
        return null;
    }

    smdIndPackageValue() {
        const v = this.hasSmdIndPackageTarget ? this.smdIndPackageTarget.value : "1210";
        return SMD_PACKAGES[v] ? v : "1210";
    }

    applySmdIndBodyColor(event) {
        if (this.hasSmdIndBodyColorTarget) {
            this.smdIndBodyColorTarget.value = event.currentTarget.dataset.color;
        }
        this.syncSmdInductor();
    }

    /*
     * ---------------------------------------------------------------
     *  Diode tab (interactive)
     * ---------------------------------------------------------------
     */

    /*
     * ---------------------------------------------------------------
     *  Helpers
     * ---------------------------------------------------------------
     */

    /**
     * Parses a human entered value like "4k7", "4.7k", "100n", "1M5" into a
     * plain number. baseUnit is "R" (ohms) or "F" (farads) and is used to strip
     * a trailing unit symbol. Returns null if it can't be parsed.
     */
    parseValue(raw, baseUnit) {
        if (raw === null || raw === undefined) {
            return null;
        }
        // Keep the original case: the prefix "m" (milli) and "M" (mega) must stay distinct.
        let s = raw.trim();
        if (s === "") {
            return null;
        }
        // Strip a trailing unit symbol (ohm, ω, f) — matched case-insensitively.
        s = s.replace(/ohm[s]?$/i, "").replace(/Ω/gi, "").trim();
        if (baseUnit === "F") {
            s = s.replace(/farad[s]?$/i, "").replace(/f$/i, "").trim();
        }

        // RKM style: prefix used as decimal separator, e.g. 4k7, 1R5, 2u2, 4M7
        let m = s.match(/^(\d+)\s*(p|n|u|µ|m|k|meg|g|r)\s*(\d+)$/i);
        if (m) {
            const factor = this.prefixFactor(m[2]);
            return factor === null ? null : parseFloat(`${m[1]}.${m[3]}`) * factor;
        }

        // Number followed by an optional prefix, e.g. 4.7k, 100n, 470, 10M
        m = s.match(/^([\d.]+)\s*(p|n|u|µ|m|k|meg|g|r)?$/i);
        if (m) {
            const num = parseFloat(m[1]);
            if (Number.isNaN(num)) {
                return null;
            }
            const factor = this.prefixFactor(m[2]);
            return factor === null ? null : num * factor;
        }

        return null;
    }

    /**
     * Resolves an SI prefix (or the RKM "R" separator) to a multiplication factor.
     * Case sensitive only for m (milli) vs M (mega); all other prefixes are
     * case-insensitive. Returns 1 for "no prefix"/R, or null for an unknown prefix.
     */
    prefixFactor(prefix) {
        if (prefix === undefined || prefix === "" || prefix.toLowerCase() === "r") {
            return 1;
        }
        if (prefix === "m") {
            return 1e-3;
        }
        if (prefix === "M") {
            return 1e6;
        }
        const factors = {p: 1e-12, n: 1e-9, u: 1e-6, "µ": 1e-6, k: 1e3, meg: 1e6, g: 1e9};
        return factors[prefix.toLowerCase()] ?? null;
    }

    formatOhms(ohms) {
        return this.formatWithPrefix(ohms, "Ω", false);
    }

    /**
     * Formats a capacitance. The input value is always given in picofarads.
     * When pfForm is true, the value is rendered in plain pF, otherwise the most
     * fitting SI prefix (pF/nF/µF/mF/F) is used.
     */
    formatFarads(pf, pfForm = false) {
        if (pfForm) {
            return `${this.trimNumber(pf)} pF`;
        }
        return this.formatWithPrefix(pf * 1e-12, "F", true);
    }

    formatWithPrefix(value, unit, isFarad) {
        if (value === 0) {
            return `0 ${unit}`;
        }
        const steps = isFarad
            ? [[1e-12, "p"], [1e-9, "n"], [1e-6, "µ"], [1e-3, "m"], [1, ""]]
            : [[1e-3, "m"], [1, ""], [1e3, "k"], [1e6, "M"], [1e9, "G"]];

        let chosen = steps[0];
        for (const step of steps) {
            if (value >= step[0]) {
                chosen = step;
            }
        }
        return `${this.trimNumber(value / chosen[0])} ${chosen[1]}${unit}`;
    }

    trimNumber(num) {
        return parseFloat(num.toFixed(3)).toString();
    }
}
