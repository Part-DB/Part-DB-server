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
import zxcvbn from "zxcvbn";

export default class extends Controller {

    _passwordInput;

    static targets = ["badge", "suggestion", "warning"]

    connect() {
        //Find the password input field
        this._passwordInput = this.element.querySelector('input[type="password"]');

        //Add event listener to the password input field
        this._passwordInput.addEventListener('input', this._onPasswordInput.bind(this));

    }

    _onPasswordInput() {
        //Retrieve the password
        const password = this._passwordInput.value;

        //Estimate the password strength
        const result = zxcvbn(password);

        //Update the badge
        this.badgeTarget.parentElement.classList.remove("d-none");
        this._setBadgeToLevel(result.score);

        this.warningTarget.innerHTML = result.feedback.warning;
    }

    _setBadgeToLevel(level) {
        let text, classes;

        switch (level) {
            case 0:
                text = "Very weak";
                classes = "bg-danger badge-danger";
                break;
            case 1:
                text = "Weak";
                classes = "bg-warning badge-warning";
                break;
            case 2:
                text = "Medium";
                classes = "bg-info badge-info";
                break;
            case 3:
                text = "Strong";
                classes = "bg-primary badge-primary";
                break;
            case 4:
                text = "Very strong";
                classes = "bg-success badge-success";
                break;
            default:
                text = "Unknown";
                classes = "bg-secondary badge-secondary";
        }

        this.badgeTarget.innerHTML = text;
        //Remove all classes
        this.badgeTarget.className = '';
        //Re-add the classes
        this.badgeTarget.classList.add("badge");
        this.badgeTarget.classList.add(...classes.split(" "));
    }
}