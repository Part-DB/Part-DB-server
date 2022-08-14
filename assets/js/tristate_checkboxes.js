'use strict';

import TristateCheckbox from "./lib/TristateCheckbox";

class TristateHelper {
    constructor() {
        this.registerTriStateCheckboxes();
    }

    registerTriStateCheckboxes() {
        //Initialize tristate checkboxes and if needed the multicheckbox functionality

        const listener = () => {

            const tristates = document.querySelectorAll("input.tristate");

            tristates.forEach(tristate => {
               TristateCheckbox.getInstance(tristate);
            });


            //Register multi checkboxes in permission tables
            const multicheckboxes = document.querySelectorAll("input.permission_multicheckbox");
            multicheckboxes.forEach(multicheckbox => {
               multicheckbox.addEventListener("change", (event) => {
                    const newValue =  TristateCheckbox.getInstance(event.target).state;
                    const row = event.target.closest("tr");

                    //Find all tristate checkboxes in the same row and set their state to the new value
                    const tristateCheckboxes = row.querySelectorAll("input.tristate");
                    tristateCheckboxes.forEach(tristateCheckbox => {
                        TristateCheckbox.getInstance(tristateCheckbox).state = newValue;
                    });
               });
            });
        }

        document.addEventListener("turbo:load", listener);
        document.addEventListener("turbo:render", listener);
    }
}

export default new TristateHelper();