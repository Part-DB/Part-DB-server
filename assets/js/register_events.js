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

import {Dropdown} from "bootstrap";

class RegisterEventHelper {
    constructor() {
        this.registerTooltips();
        this.configureDropdowns();
        this.registerSpecialCharInput();

        this.registerModalDropRemovalOnFormSubmit();
    }

    registerModalDropRemovalOnFormSubmit() {
        //Remove all modal backdrops, before rendering the new page.
        document.addEventListener('turbo:before-render', event => {
            const back_drop = document.querySelector('.modal-backdrop');
            if (back_drop) {
                back_drop.remove();
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
        this.registerLoadHandler(() => {
            $(".tooltip").remove();
            //Exclude dropdown buttons from tooltips, otherwise we run into endless errors from bootstrap (bootstrap.esm.js:614 Bootstrap doesn't allow more than one instance per element. Bound instance: bs.dropdown.)
            $('a[title], button[title]:not([data-bs-toggle="dropdown"]), p[title], span[title], h6[title], h3[title], i.fas[title]')
                //@ts-ignore
                .tooltip("hide").tooltip({container: "body", placement: "auto", boundary: 'window'});
        });
    }

    registerSpecialCharInput() {
        this.registerLoadHandler(() => {
            //@ts-ignore
            $("input[type=text], input[type=search]").unbind("keydown").keydown(function (event) {
                let use_special_char = event.altKey;

                let greek_char = "";
                if (use_special_char){
                    //Use the key property to determine the greek letter (as it is independent of the keyboard layout)
                    switch(event.key) {
                        //Greek letters
                        case "a": //Alpha (lowercase)
                            greek_char = "\u03B1";
                            break;
                        case "A": //Alpha (uppercase)
                            greek_char = "\u0391";
                            break;
                        case "b": //Beta (lowercase)
                            greek_char = "\u03B2";
                            break;
                        case "B": //Beta (uppercase)
                            greek_char = "\u0392";
                            break;
                        case "g": //Gamma (lowercase)
                            greek_char = "\u03B3";
                            break;
                        case "G": //Gamma (uppercase)
                            greek_char = "\u0393";
                            break;
                        case "d": //Delta (lowercase)
                            greek_char = "\u03B4";
                            break;
                        case "D": //Delta (uppercase)
                            greek_char = "\u0394";
                            break;
                        case "e": //Epsilon (lowercase)
                            greek_char = "\u03B5";
                            break;
                        case "E": //Epsilon (uppercase)
                            greek_char = "\u0395";
                            break;
                        case "z": //Zeta (lowercase)
                            greek_char = "\u03B6";
                            break;
                        case "Z": //Zeta (uppercase)
                            greek_char = "\u0396";
                            break;
                        case "h": //Eta (lowercase)
                            greek_char = "\u03B7";
                            break;
                        case "H": //Eta (uppercase)
                            greek_char = "\u0397";
                            break;
                        case "q": //Theta (lowercase)
                            greek_char = "\u03B8";
                            break;
                        case "Q": //Theta (uppercase)
                            greek_char = "\u0398";
                            break;
                        case "i": //Iota (lowercase)
                            greek_char = "\u03B9";
                            break;
                        case "I": //Iota (uppercase)
                            greek_char = "\u0399";
                            break;
                        case "k": //Kappa (lowercase)
                            greek_char = "\u03BA";
                            break;
                        case "K": //Kappa (uppercase)
                            greek_char = "\u039A";
                            break;
                        case "l": //Lambda (lowercase)
                            greek_char = "\u03BB";
                            break;
                        case "L": //Lambda (uppercase)
                            greek_char = "\u039B";
                            break;
                        case "m": //Mu (lowercase)
                            greek_char = "\u03BC";
                            break;
                        case "M": //Mu (uppercase)
                            greek_char = "\u039C";
                            break;
                        case "n": //Nu (lowercase)
                            greek_char = "\u03BD";
                            break;
                        case "N": //Nu (uppercase)
                            greek_char = "\u039D";
                            break;
                        case "x": //Xi (lowercase)
                            greek_char = "\u03BE";
                            break;
                        case "X": //Xi (uppercase)
                            greek_char = "\u039E";
                            break;
                        case "o": //Omicron (lowercase)
                            greek_char = "\u03BF";
                            break;
                        case "O": //Omicron (uppercase)
                            greek_char = "\u039F";
                            break;
                        case "p": //Pi (lowercase)
                            greek_char = "\u03C0";
                            break;
                        case "P": //Pi (uppercase)
                            greek_char = "\u03A0";
                            break;
                        case "r": //Rho (lowercase)
                            greek_char = "\u03C1";
                            break;
                        case "R": //Rho (uppercase)
                            greek_char = "\u03A1";
                            break;
                        case "s": //Sigma (lowercase)
                            greek_char = "\u03C3";
                            break;
                        case "S": //Sigma (uppercase)
                            greek_char = "\u03A3";
                            break;
                        case "t": //Tau (lowercase)
                            greek_char = "\u03C4";
                            break;
                        case "T": //Tau (uppercase)
                            greek_char = "\u03A4";
                            break;
                        case "u": //Upsilon (lowercase)
                            greek_char = "\u03C5";
                            break;
                        case "U": //Upsilon (uppercase)
                            greek_char = "\u03A5";
                            break;
                        case "f": //Phi (lowercase)
                            greek_char = "\u03C6";
                            break;
                        case "F": //Phi (uppercase)
                            greek_char = "\u03A6";
                            break;
                        case "c": //Chi (lowercase)
                            greek_char = "\u03C7";
                            break;
                        case "C": //Chi (uppercase)
                            greek_char = "\u03A7";
                            break;
                        case "y": //Psi (lowercase)
                            greek_char = "\u03C8";
                            break;
                        case "Y": //Psi (uppercase)
                            greek_char = "\u03A8";
                            break;
                        case "w": //Omega (lowercase)
                            greek_char = "\u03C9";
                            break;
                        case "W": //Omega (uppercase)
                            greek_char = "\u03A9";
                            break;
                    }

                    //Use keycodes for special characters as the shift char on the number keys are layout dependent
                    switch (event.keyCode) {
                        case 49: //1 key
                            //Product symbol on shift, sum on no shift
                            greek_char = event.shiftKey ? "\u220F" : "\u2211";
                            break;
                        case 50: //2 key
                            //Integral on no shift, partial derivative on shift
                            greek_char = event.shiftKey ?  "\u2202" : "\u222B";
                            break;
                        case 51: //3 key
                            //Less than or equal on no shift, greater than or equal on shift
                            greek_char = event.shiftKey ? "\u2265" : "\u2264";
                            break;
                        case 52: //4 key
                            //Empty set on shift, infinity on no shift
                            greek_char = event.shiftKey ? "\u2205" : "\u221E";
                            break;
                        case 53: //5 key
                            //Not equal on shift, approx equal on no shift
                            greek_char = event.shiftKey ? "\u2260" : "\u2248";
                            break;
                        case 54: //6 key
                            //Element of on no shift, not element of on shift
                            greek_char = event.shiftKey ? "\u2209" : "\u2208";
                            break;
                        case 55: //7 key
                            //And on shift, or on no shift
                            greek_char = event.shiftKey ? "\u2227" : "\u2228";
                            break;
                        case 56: //8 key
                            //Proportional to on shift, angle on no shift
                            greek_char = event.shiftKey ? "\u221D" : "\u2220";
                            break;
                        case 57: //9 key
                            //Cube root on shift, square root on no shift
                            greek_char = event.shiftKey ? "\u221B" : "\u221A";
                            break;
                        case 48: //0 key
                            //Minus-Plus on shift, plus-minus on no shift
                            greek_char = event.shiftKey ? "\u2213" : "\u00B1";
                            break;

                        //Special characters
                        case 219: //hyphen (or ß on german layout)
                            //Copyright on no shift, TM on shift
                            greek_char = event.shiftKey ? "\u2122" : "\u00A9";
                            break;
                        case 191: //forward slash (or # on german layout)
                            //Generic currency on no shift, paragraph on shift
                            greek_char = event.shiftKey ? "\u00B6" : "\u00A4";
                            break;

                        //Currency symbols
                        case 192: //: or (ö on german layout)
                            //Euro on no shift, pound on shift
                            greek_char = event.shiftKey ? "\u00A3" : "\u20AC";
                            break;
                        case 221: //; or (ä on german layout)
                            //Yen on no shift,  dollar on shift
                            greek_char = event.shiftKey ? "\u0024" : "\u00A5";
                            break;


                    }

                    if(greek_char=="") return;

                    let $txt = $(this);
                    //@ts-ignore
                    let caretPos = $txt[0].selectionStart;
                    let textAreaTxt = $txt.val().toString();
                    $txt.val(textAreaTxt.substring(0, caretPos) + greek_char + textAreaTxt.substring(caretPos) );

                }
            });
            //@ts-ignore
            this.greek_once = true;
        })
    }
}

export default new RegisterEventHelper();