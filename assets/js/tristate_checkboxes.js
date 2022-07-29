'use strict';

import "./lib/jquery.tristate"

class TristateHelper {
    constructor() {
        this.registerTriStateCheckboxes();
        this.registerSubmitHandler();
    }

    registerSubmitHandler() {
        document.addEventListener("turbo:submit-start", (e) => {
            var form = e.detail.formSubmission.formElement;
            var formData = e.detail.formSubmission.formData;

            var $tristate_checkboxes = $('.tristate:checkbox', form);

            //Iterate over each tristate checkbox in the form and set formData to the correct value
            $tristate_checkboxes.each(function() {
                var $checkbox = $(this);
                var state = $checkbox.tristate('state');

                formData.set($checkbox.attr('name'), state);
            });
        });
    }

    registerTriStateCheckboxes() {
        //Initialize tristate checkboxes and if needed the multicheckbox functionality
        document.addEventListener("turbo:load", () => {
            $(".tristate").tristate( {
                checked:            "true",
                unchecked:          "false",
                indeterminate:      "indeterminate",
            });

            $('.permission_multicheckbox:checkbox').change(function() {
                //Find the other checkboxes in this row, and change their value
                var $row = $(this).parents('tr');

                //@ts-ignore
                var new_state = $(this).tristate('state');

                //@ts-ignore
                $('.tristate:checkbox', $row).tristate('state', new_state);
            });
        })
    }
}

export default new TristateHelper();