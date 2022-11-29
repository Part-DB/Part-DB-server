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
import Darkmode from "darkmode-js/src";
import "darkmode-js"

export default class extends Controller {

    _darkmode;

    connect() {
        if (typeof window.getComputedStyle(document.body).mixBlendMode == 'undefined') {
            console.warn("The browser does not support mix blend mode. Darkmode will not work.");
            return;
        }

        try {
            const darkmode = new Darkmode();
            this._darkmode = darkmode;

            //Unhide darkmode button
            this._showWidget();

            //Set the switch according to our current darkmode state
            const toggler = document.getElementById("toggleDarkmode");
            toggler.checked = darkmode.isActivated();
        }
        catch (e)
        {
            console.error(e);
        }


    }

    _showWidget() {
        this.element.classList.remove('hidden');
    }

    toggleDarkmode() {
        this._darkmode.toggle();
    }
}