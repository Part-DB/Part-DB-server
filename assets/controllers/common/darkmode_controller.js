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

export default class extends Controller {

    connect() {
        this.setMode(this.getMode());
        document.querySelectorAll('input[name="darkmode"]').forEach((radio) => {
            radio.addEventListener('change', this._radioChanged.bind(this));
        });
    }

    /**
     * Event listener for the change of radio buttons
     * @private
     */
    _radioChanged(event) {
        const new_mode = this.getSelectedMode();
        this.setMode(new_mode);
    }

    /**
     * Get the current mode from the local storage
     * @return {'dark', 'light', 'auto'}
     */
    getMode() {
        return localStorage.getItem('darkmode') ?? 'auto';
    }

    /**
     * Set the mode in the local storage and apply it and change the state of the radio buttons
     * @param mode
     */
    setMode(mode) {
        if (mode !== 'dark' && mode !== 'light' && mode !== 'auto') {
            console.warn('Invalid darkmode mode: ' + mode);
            mode = 'auto';
        }

        localStorage.setItem('darkmode', mode);

        this.setButtonMode(mode);

        if (mode === 'auto') {
            this._setDarkmodeAuto();
        } else if (mode === 'dark') {
            this._enableDarkmode();
        } else if (mode === 'light') {
            this._disableDarkmode();
        }
    }

    /**
     * Get the selected mode via the radio buttons
     * @return {'dark', 'light', 'auto'}
     */
    getSelectedMode() {
        return document.querySelector('input[name="darkmode"]:checked').value;
    }

    /**
     * Set the state of the radio buttons
     * @param mode
     */
    setButtonMode(mode) {
        document.querySelector('input[name="darkmode"][value="' + mode + '"]').checked = true;
    }

    /**
     * Enable darkmode by adding the data-bs-theme="dark" to the html tag
     * @private
     */
    _enableDarkmode() {
        //Add data-bs-theme="dark" to the html tag
        document.documentElement.setAttribute('data-bs-theme', 'dark');
    }

    /**
     * Disable darkmode by adding the data-bs-theme="light" to the html tag
     * @private
     */
    _disableDarkmode() {
        //Set data-bs-theme to light
        document.documentElement.setAttribute('data-bs-theme', 'light');
    }


    /**
     * Set the darkmode to auto and enable/disable it depending on the system settings, also add
     * an event listener to change the darkmode if the system settings change
     * @private
     */
    _setDarkmodeAuto() {
        if (this.getMode() !== 'auto') {
            return;
        }

        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            this._enableDarkmode();
        } else {
            this._disableDarkmode();
        }

        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', event => {
            console.log('Prefered color scheme changed to ' + event.matches ? 'dark' : 'light');
            this._setDarkmodeAuto();
        });
    }

    /**
     * Check if darkmode is activated
     * @return {boolean}
     */
    isDarkmodeActivated() {
        return document.documentElement.getAttribute('data-bs-theme') === 'dark';
    }
}