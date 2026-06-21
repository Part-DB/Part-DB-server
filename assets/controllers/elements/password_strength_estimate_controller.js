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
import { ZxcvbnFactory } from '@zxcvbn-ts/core';
import * as zxcvbnCommonPackage from '@zxcvbn-ts/language-common';
import * as zxcvbnEnPackage from '@zxcvbn-ts/language-en';
import * as zxcvbnDePackage from '@zxcvbn-ts/language-de';
import * as zxcvbnFrPackage from '@zxcvbn-ts/language-fr';
import * as zxcvbnJaPackage from '@zxcvbn-ts/language-ja';
import * as zxcvbnItPackage from '@zxcvbn-ts/language-it';
import * as zxcvbnPlPackage from '@zxcvbn-ts/language-pl';
import {trans} from '../../translator.js';

/* stimulusFetch: 'lazy' */
export default class extends Controller {

    _passwordInput;

    static targets = ["badge", "warning"]

    _zxcvbnFactory;

    _getTranslations() {
        //Get the current locale
        const locale = document.documentElement.lang;
        if (locale.includes('de')) {
            return zxcvbnDePackage.translations;
        } else if (locale.includes('fr')) {
            return zxcvbnFrPackage.translations;
        } else if (locale.includes('ja')) {
            return zxcvbnJaPackage.translations;
        } else if (locale.includes('it')) {
            return zxcvbnItPackage.translations;
        } else if (locale.includes('pl')) {
            return zxcvbnPlPackage.translations;
        }

        //Fallback to english
        return zxcvbnEnPackage.translations;
    }

    connect() {
        //Find the password input field
        this._passwordInput = this.element.querySelector('input[type="password"]');

        //Configure zxcvbn
        const options = {
            graphs: zxcvbnCommonPackage.adjacencyGraphs,
            useLevenshtein: true,
            dictionary: {
                ...zxcvbnCommonPackage.dictionary,
                // We could use the english dictionary here too, but it is very big. So we just use the common words
                ...zxcvbnEnPackage.dictionary,
                ...zxcvbnDePackage.dictionary,

                "partdb": ['part-db', 'partdb', 'part_db', 'part-db-symfony', 'partdb-symfony', 'part_db_symfony'],
            },
            translations: this._getTranslations(),
        };

        this._zxcvbnFactory = new ZxcvbnFactory(options);

        //Add event listener to the password input field
        this._passwordInput.addEventListener('input', this._onPasswordInput.bind(this));
    }

    async _onPasswordInput() {
        //Retrieve the password
        const password = this._passwordInput.value;

        //Estimate the password strength
        const result = await this._zxcvbnFactory.checkAsync(password);

        //Update the badge
        this.badgeTarget.parentElement.classList.remove("d-none");
        this._setBadgeToLevel(result.score, result.crackTimes.onlineNoThrottlingXPerSecond.display);

        this.warningTarget.innerHTML = result.feedback.warning;
    }

    _setBadgeToLevel(level, time = null) {
        let text, classes;

        switch (level) {
            case 0:
                text = trans("user.password_strength.very_weak");
                classes = "bg-danger badge-danger";
                break;
            case 1:
                text = trans("user.password_strength.weak");
                classes = "bg-warning badge-warning";
                break;
            case 2:
                text = trans("user.password_strength.medium");
                classes = "bg-info badge-info";
                break;
            case 3:
                text = trans("user.password_strength.strong");
                classes = "bg-primary badge-primary";
                break;
            case 4:
                text = trans("user.password_strength.very_strong");
                classes = "bg-success badge-success";
                break;
            default:
                text = "???";
                classes = "bg-secondary badge-secondary";
        }

        this.badgeTarget.innerHTML = text;
        //Remove all classes
        this.badgeTarget.className = '';
        //Re-add the classes
        this.badgeTarget.classList.add("badge");
        this.badgeTarget.classList.add(...classes.split(" "));

        if (time) {
            this.badgeTarget.setAttribute("title", trans("user.password_strength.crack_time", {"%time%": time}));
        } else {
            this.badgeTarget.removeAttribute("title");
        }
    }
}
