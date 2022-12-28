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

import * as bootbox from "bootbox";
import "../../css/components/bootbox_extensions.css";

export default class extends Controller {
    static values = {
        deleteMessage: String,
        prototype: String,
        rowsToDelete: Number, //How many rows (including the current one) shall be deleted after the current row
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

        if(!prototype) {
            console.warn("Prototype is not set, we cannot create a new element. This is most likely due to missing permissions.");
            bootbox.alert("You do not have the permsissions to create a new element. (No protoype element is set)");
            return;
        }


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
        const del = () => {
            const target = event.target;
            //Remove the row element from the table
            const current_row = target.closest("tr");
            for(let i = this.rowsToDeleteValue; i > 1; i--) {
                let nextSibling = current_row.nextElementSibling;
                //Ensure that nextSibling is really a tr
                if (nextSibling && nextSibling.tagName === "TR") {
                    nextSibling.remove();
                }
            }

            //Finally delete the current row
            current_row.remove();
        }

        if(this.deleteMessageValue) {
            bootbox.confirm(this.deleteMessageValue, (result) => {
                if (result) {
                    del();
                }
            });
        } else {
            del();
        }
    }
}