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

    /**
     * Similar to createEvent Pricedetails need some special handling to fill min amount
     * @param event
     */
    createPricedetail(event) {
        //First insert our new element
        this.createElement(event);

        const extractElementsFromRow = (row) => {
            const priceRelated = row.querySelector("input[id$='price_related_quantity_value']");
            const minDiscount = row.querySelector("input[id$='min_discount_quantity_value']");

            return [priceRelated, minDiscount];
        }

        const targetTable = this.targetTarget;
        const targetRows = targetTable.tBodies[0].rows;
        const targetRowsCount = targetRows.length;

        //If we just have one element we dont have to do anything as 1 is already the default
        if(targetRowsCount <= 1) {
            return;
        }

        //Our new element is the last child of the table
        const newlyCreatedRow = targetRows[targetRowsCount - 1];
        const [newPriceRelated, newMinDiscount] = extractElementsFromRow(newlyCreatedRow);

        const oldRow = targetRows[targetRowsCount - 2];
        const [oldPriceRelated, oldMinDiscount] = extractElementsFromRow(oldRow);

        //Use the old PriceRelated value to determine the next 10 decade value for the new row
        const oldMinAmount = parseInt(oldMinDiscount.value)
        //The next 10 power can be achieved by creating a string beginning with "1" and adding 0 times the length of the old string
        const oldMinAmountLength = oldMinAmount.toString().length;
        const newMinAmountStr = '1' + '0'.repeat(oldMinAmountLength);
        //Parse the sting back to an integer and we have our new min amount
        const newMinAmount = parseInt(newMinAmountStr);


        //Assign it to our new element
        newMinDiscount.value = newMinAmount;
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