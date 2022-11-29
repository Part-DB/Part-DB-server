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

import { Controller } from '@hotwired/stimulus';
import { marked } from "marked";
import DOMPurify from 'dompurify';

import "../../css/markdown.css";

export default class extends Controller {

    connect()
    {
        this.configureMarked();
        this.render();

        //Dispatch an event that we are now finished
        const event = new CustomEvent('markdown:finished', {
           bubbles: true
        });
        this.element.dispatchEvent(event);
    }

    render() {
        let raw = this.element.dataset['markdown'];

        //Apply purified parsed markdown
        this.element.innerHTML = DOMPurify.sanitize(marked(this.unescapeHTML(raw)));

        for(let a of this.element.querySelectorAll('a')) {
            //Mark all links as external
            a.classList.add('link-external');
            //Open links in new tag
            a.setAttribute('target', '_blank');
            //Dont track
            a.setAttribute('rel', 'noopener');
        }

        //Apply bootstrap styles to tables
        for(let table of this.element.querySelectorAll('table')) {
            table.classList.add('table', 'table-hover', 'table-striped', 'table-bordered', 'table-sm');
        }

        //Make header line dark
        for(let head of this.element.querySelectorAll('thead')) {
            head.classList.add('table-dark');
        }
    }

    /**
     * Unescape the given HTML
     * @param {string} html
     * @returns {string}
     */
    unescapeHTML(html) {
        var txt = document.createElement('textarea');
        txt.innerHTML = html;
        return txt.value;
    }

    /**
     * Configure the marked parser
     */
    configureMarked()
    {
        marked.setOptions({
            gfm: true,
        });
    }
}