'use strict';

import { Controller } from '@hotwired/stimulus';
import * as marked from "marked";
import DOMPurify from 'dompurify';

export default class extends Controller {

    connect()
    {
        console.log('Markdown controller called');
        this.render();
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

        //Apply bootstrap styles to
        for(let table of this.element.querySelectorAll('table')) {
            table.classList.add('table', 'table-hover', 'table-striped', 'table-bordered', 'table-sm');
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