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

class RegisterEventHelper {
    constructor() {
        this.registerTooltips();

        this.registerSpecialCharInput();
    }

    registerLoadHandler(fn) {
        document.addEventListener('turbo:render', fn);
        document.addEventListener('turbo:load', fn);
    }

    registerTooltips() {
        this.registerLoadHandler(() => {
            $(".tooltip").remove();
            //Exclude dropdown buttons from tooltips, otherwise we run into endless errors from bootstrap (bootstrap.esm.js:614 Bootstrap doesn't allow more than one instance per element. Bound instance: bs.dropdown.)
            $('a[title], button[title]:not([data-bs-toggle="dropdown"]), span[title], h6[title], h3[title], i.fas[title]')
                //@ts-ignore
                .tooltip("hide").tooltip({container: "body", placement: "auto", boundary: 'window'});
        });
    }

    registerSpecialCharInput() {
        this.registerLoadHandler(() => {
            //@ts-ignore
            $("input[type=text], input[type=search]").unbind("keydown").keydown(function (event) {
                let greek = event.altKey;

                let greek_char = "";
                if (greek){
                    switch(event.key) {
                        case "w": //Omega
                            greek_char = '\u2126';
                            break;
                        case "u":
                        case "m": //Micro
                            greek_char = "\u00B5";
                            break;
                        case "p": //Phi
                            greek_char = "\u03C6";
                            break;
                        case "a": //Alpha
                            greek_char = "\u03B1";
                            break;
                        case "b": //Beta
                            greek_char = "\u03B2";
                            break;
                        case "c": //Gamma
                            greek_char = "\u03B3";
                            break;
                        case "d": //Delta
                            greek_char = "\u03B4";
                            break;
                        case "l": //Pound
                            greek_char = "\u00A3";
                            break;
                        case "y": //Yen
                            greek_char = "\u00A5";
                            break;
                        case "o": //Yen
                            greek_char = "\u00A4";
                            break;
                        case "1": //Sum symbol
                            greek_char = "\u2211";
                            break;
                        case "2": //Integral
                            greek_char = "\u222B";
                            break;
                        case "3": //Less-than or equal
                            greek_char = "\u2264";
                            break;
                        case "4": //Greater than or equal
                            greek_char = "\u2265";
                            break;
                        case "5": //PI
                            greek_char = "\u03c0";
                            break;
                        case "q": //Copyright
                            greek_char = "\u00A9";
                            break;
                        case "e": //Euro
                            greek_char = "\u20AC";
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