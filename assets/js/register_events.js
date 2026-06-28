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

'use strict';

import {Dropdown, Modal, Tooltip} from "bootstrap";
import ClipboardJS from "clipboard";

class RegisterEventHelper {
    constructor() {
        this.registerTooltips();
        this.configureDropdowns();

        // Only register special character input if enabled in configuration
        const keybindingsEnabled = document.body.dataset.keybindingsSpecialCharacters !== 'false';
        if (keybindingsEnabled) {
            this.registerSpecialCharInput();
        }

        //Initialize ClipboardJS
        this.registerLoadHandler(() => {
            new ClipboardJS('.btn');
        });

        this.registerModalDropRemovalOnFormSubmit();
    }

    registerModalDropRemovalOnFormSubmit() {
        //Remove all modal backdrops, before rendering the new page.
        document.addEventListener('turbo:before-render', event => {
            const back_drop = document.querySelector('.modal-backdrop');
            if (back_drop) {
                back_drop.remove();
            }

            //Remove scroll-lock if it is still active
            if (document.body.classList.contains('modal-open')) {
                document.body.classList.remove('modal-open');

                //Remove the padding-right and overflow:hidden from the body
                document.body.style.paddingRight = '';
                document.body.style.overflow = '';
            }
        });
    }

    registerLoadHandler(fn) {
        document.addEventListener('turbo:render', fn);
        document.addEventListener('turbo:load', fn);
    }

    configureDropdowns() {
        this.registerLoadHandler(() => {
            //Set the dropdown strategy to fixed, so that the dropdowns are not cut off by the overflow: hidden of the body.
            //Solution from: https://github.com/twbs/bootstrap/issues/36560
            const dropdowns = document.querySelectorAll('[data-bs-toggle="dropdown"]');
            const dropdown = [...dropdowns].map((dropdownToggleEl) => new Dropdown(dropdownToggleEl, {
                popperConfig(defaultBsPopperConfig) {
                    return { ...defaultBsPopperConfig, strategy: 'fixed' };
                }
            }));
        });
    }

    registerTooltips() {
        const handler = () => {
            document.querySelectorAll('.tooltip').forEach(el => el.remove());

            //Exclude dropdown buttons from tooltips, otherwise we run into endless errors from bootstrap (bootstrap.esm.js:614 Bootstrap doesn't allow more than one instance per element. Bound instance: bs.dropdown.)
            const tooltipSelector = 'a[title], label[title], button[title]:not([data-bs-toggle="dropdown"]), p[title], span[title], h6[title], h3[title], i[title], small[title]';
            document.querySelectorAll(tooltipSelector).forEach(el => {
                const existing = Tooltip.getInstance(el);
                if (existing) {
                    existing.dispose();
                }
                new Tooltip(el, {container: 'body', placement: 'auto', boundary: 'window'});
            });
        };

        this.registerLoadHandler(handler);
        document.addEventListener('dt:loaded', handler);
    }

    registerSpecialCharInput() {
        const keydownHandler = function(event) {
            let use_special_char = event.altKey;

            let greek_char = "";
            if (use_special_char){
                //Use the key property to determine the greek letter (as it is independent of the keyboard layout)
                switch(event.key) {
                    //Greek letters
                    case "a": //Alpha (lowercase)
                        greek_char = "α";
                        break;
                    case "A": //Alpha (uppercase)
                        greek_char = "Α";
                        break;
                    case "b": //Beta (lowercase)
                        greek_char = "β";
                        break;
                    case "B": //Beta (uppercase)
                        greek_char = "Β";
                        break;
                    case "g": //Gamma (lowercase)
                        greek_char = "γ";
                        break;
                    case "G": //Gamma (uppercase)
                        greek_char = "Γ";
                        break;
                    case "d": //Delta (lowercase)
                        greek_char = "δ";
                        break;
                    case "D": //Delta (uppercase)
                        greek_char = "Δ";
                        break;
                    case "e": //Epsilon (lowercase)
                        greek_char = "ε";
                        break;
                    case "E": //Epsilon (uppercase)
                        greek_char = "Ε";
                        break;
                    case "z": //Zeta (lowercase)
                        greek_char = "ζ";
                        break;
                    case "Z": //Zeta (uppercase)
                        greek_char = "Ζ";
                        break;
                    case "h": //Eta (lowercase)
                        greek_char = "η";
                        break;
                    case "H": //Eta (uppercase)
                        greek_char = "Η";
                        break;
                    case "q": //Theta (lowercase)
                        greek_char = "θ";
                        break;
                    case "Q": //Theta (uppercase)
                        greek_char = "Θ";
                        break;
                    case "i": //Iota (lowercase)
                        greek_char = "ι";
                        break;
                    case "I": //Iota (uppercase)
                        greek_char = "Ι";
                        break;
                    case "k": //Kappa (lowercase)
                        greek_char = "κ";
                        break;
                    case "K": //Kappa (uppercase)
                        greek_char = "Κ";
                        break;
                    case "l": //Lambda (lowercase)
                        greek_char = "λ";
                        break;
                    case "L": //Lambda (uppercase)
                        greek_char = "Λ";
                        break;
                    case "m": //Mu (lowercase)
                        greek_char = "μ";
                        break;
                    case "M": //Mu (uppercase)
                        greek_char = "Μ";
                        break;
                    case "n": //Nu (lowercase)
                        greek_char = "ν";
                        break;
                    case "N": //Nu (uppercase)
                        greek_char = "Ν";
                        break;
                    case "x": //Xi (lowercase)
                        greek_char = "ξ";
                        break;
                    case "X": //Xi (uppercase)
                        greek_char = "Ξ";
                        break;
                    case "o": //Omicron (lowercase)
                        greek_char = "ο";
                        break;
                    case "O": //Omicron (uppercase)
                        greek_char = "Ο";
                        break;
                    case "p": //Pi (lowercase)
                        greek_char = "π";
                        break;
                    case "P": //Pi (uppercase)
                        greek_char = "Π";
                        break;
                    case "r": //Rho (lowercase)
                        greek_char = "ρ";
                        break;
                    case "R": //Rho (uppercase)
                        greek_char = "Ρ";
                        break;
                    case "s": //Sigma (lowercase)
                        greek_char = "σ";
                        break;
                    case "S": //Sigma (uppercase)
                        greek_char = "Σ";
                        break;
                    case "t": //Tau (lowercase)
                        greek_char = "τ";
                        break;
                    case "T": //Tau (uppercase)
                        greek_char = "Τ";
                        break;
                    case "u": //Upsilon (lowercase)
                        greek_char = "υ";
                        break;
                    case "U": //Upsilon (uppercase)
                        greek_char = "Υ";
                        break;
                    case "f": //Phi (lowercase)
                        greek_char = "φ";
                        break;
                    case "F": //Phi (uppercase)
                        greek_char = "Φ";
                        break;
                    case "c": //Chi (lowercase)
                        greek_char = "χ";
                        break;
                    case "C": //Chi (uppercase)
                        greek_char = "Χ";
                        break;
                    case "y": //Psi (lowercase)
                        greek_char = "ψ";
                        break;
                    case "Y": //Psi (uppercase)
                        greek_char = "Ψ";
                        break;
                    case "w": //Omega (lowercase)
                        greek_char = "ω";
                        break;
                    case "W": //Omega (uppercase)
                        greek_char = "Ω";
                        break;
                }

                //Use keycodes for special characters as the shift char on the number keys are layout dependent
                switch (event.keyCode) {
                    case 49: //1 key
                        //Product symbol on shift, sum on no shift
                        greek_char = event.shiftKey ? "∏" : "∑";
                        break;
                    case 50: //2 key
                        //Integral on no shift, partial derivative on shift
                        greek_char = event.shiftKey ?  "∂" : "∫";
                        break;
                    case 51: //3 key
                        //Less than or equal on no shift, greater than or equal on shift
                        greek_char = event.shiftKey ? "≥" : "≤";
                        break;
                    case 52: //4 key
                        //Empty set on shift, infinity on no shift
                        greek_char = event.shiftKey ? "∅" : "∞";
                        break;
                    case 53: //5 key
                        //Not equal on shift, approx equal on no shift
                        greek_char = event.shiftKey ? "≠" : "≈";
                        break;
                    case 54: //6 key
                        //Element of on no shift, not element of on shift
                        greek_char = event.shiftKey ? "∉" : "∈";
                        break;
                    case 55: //7 key
                        //And on shift, or on no shift
                        greek_char = event.shiftKey ? "∧" : "∨";
                        break;
                    case 56: //8 key
                        //Proportional to on shift, angle on no shift
                        greek_char = event.shiftKey ? "∝" : "∠";
                        break;
                    case 57: //9 key
                        //Cube root on shift, square root on no shift
                        greek_char = event.shiftKey ? "∛" : "√";
                        break;
                    case 48: //0 key
                        //Minus-Plus on shift, plus-minus on no shift
                        greek_char = event.shiftKey ? "∓" : "±";
                        break;

                    //Special characters
                    case 219: //hyphen (or ß on german layout)
                        //Copyright on no shift, TM on shift
                        greek_char = event.shiftKey ? "™" : "©";
                        break;
                    case 191: //forward slash (or # on german layout)
                        //Generic currency on no shift, paragraph on shift
                        greek_char = event.shiftKey ? "¶" : "¤";
                        break;

                    //Currency symbols
                    case 192: //: or (ö on german layout)
                        //Euro on no shift, pound on shift
                        greek_char = event.shiftKey ? "£" : "€";
                        break;
                    case 221: //; or (ä on german layout)
                        //Yen on no shift,  dollar on shift
                        greek_char = event.shiftKey ? "$" : "¥";
                        break;
                }

                if(greek_char=="") return;

                const txt = event.currentTarget;
                const caretPos = txt.selectionStart;
                const textAreaTxt = txt.value;
                txt.value = textAreaTxt.substring(0, caretPos) + greek_char + textAreaTxt.substring(caretPos);
            }
        };

        this.registerLoadHandler(() => {
            document.querySelectorAll('input[type=text], input[type=search]').forEach(input => {
                input.removeEventListener('keydown', keydownHandler);
                input.addEventListener('keydown', keydownHandler);
            });
        });
    }
}

export default new RegisterEventHelper();
