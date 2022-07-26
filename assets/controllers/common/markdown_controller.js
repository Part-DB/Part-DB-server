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