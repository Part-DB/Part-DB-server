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

export default class extends Controller
{
    static targets = ['link', 'mode', 'otherSelect'];
    static values = {
        targetId: Number,
    };

    connect() {
    }

    update() {
        const link = this.linkTarget;
        const other_select = this.otherSelectTarget;

        //Extract the mode using the mode radio buttons (we filter the array to get the checked one)
        const mode = (this.modeTargets.filter((e)=>e.checked))[0].value;

        if (other_select.value === '') {
            link.classList.add('disabled');
            return;
        }

        //Extract href template from data attribute on link target
        let href = link.getAttribute('data-href-template');

        let target, other;
        if (mode === '1') {
            target = this.targetIdValue;
            other = other_select.value;
        } else if (mode === '2') {
            target = other_select.value;
            other = this.targetIdValue;
        } else {
            throw 'Invalid mode';
        }

        //Replace placeholder with actual target id
        href = href.replace('__target__', target);
        //Replace placeholder with selected value of the select (the event sender)
        href = href.replace('__other__', other);

        //Assign new href to link
        link.setAttribute('href', href);
        //Make link clickable
        link.classList.remove('disabled');
    }
}