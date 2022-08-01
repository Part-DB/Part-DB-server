import {Controller} from "@hotwired/stimulus";

import * as bootbox from "bootbox";
import "../../css/bootbox_extensions.css";

export default class extends Controller {
    static values = {
        deleteMessage: String,
        prototype: String,
    }

    static targets = ["target"];

    /**
     * Decodes escaped HTML entities
     * @param {string} input
     * @returns {string}
     */
    htmlDecode(input) {
        const doc = new DOMParser().parseFromString(input, "text/html");
        return doc.documentElement.textContent;
    }

    /**
     * Generates a unique ID to be used for the new element
     * @returns {string}
     */
    generateUID() {
        const long = (performance.now().toString(36)+Math.random().toString(36)).replace(/\./g,"");
        return long.slice(0, 6); // 6 characters is enough for our unique IDs here
    }

    /**
     * Create a new entry in the target using the given prototype value
     */
    createElement(event) {
        const targetTable = this.targetTarget;
        const prototype = this.prototypeValue

        //Apply the index to prototype to create our element to insert
        const newElementStr = this.htmlDecode(prototype.replace(/__name__/g, this.generateUID()));


        //Insert new html after the last child element
        //If the table has a tbody, insert it there
        if(targetTable.tBodies[0]) {
            targetTable.tBodies[0].insertAdjacentHTML('beforeend', newElementStr);
        } else { //Otherwise just insert it
            targetTable.insertAdjacentHTML('beforeend', newElementStr);
        }
    }

    deleteElement(event) {
        bootbox.confirm(this.deleteMessageValue, (result) => {
            if(result) {
                const target = event.target;
                //Remove the row element from the table
                target.closest("tr").remove();
            }
        });
    }
}