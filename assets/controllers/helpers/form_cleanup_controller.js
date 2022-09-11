import {Controller} from "@hotwired/stimulus";

/**
 * Purpose of this controller is to clean up the form before it is finally submitted. This means empty fields get disabled, so they are not submitted.
 * This is especially useful for GET forms, to prevent very long URLs
 */
export default class extends Controller {

    /**
     * Call during the submit event of the form. This will disable all empty fields, so they are not submitted.
     * @param event
     */
    submit(event) {
        /** Find the form this event belongs to */
        /** @type {HTMLFormElement} */
        const form = event.target.closest('form');

        for(const element of form.elements) {
            if(! element.value) {
                element.disabled = true;
            }

            //Workaround for tristate checkboxes which use a hidden field to store the value
            if ((element.type === 'hidden' || element.type === 'checkbox') && element.value === 'indeterminate') {
                element.disabled = true;
            }
        }
    }

    /**
     * Submits the form with all form elements disabled, so they are not submitted. This is useful for GET forms, to reset the form to not filled state.
     * @param event
     */
    clearAll(event)
    {
        const form = event.target.closest('form');
        for(const element of form.elements) {
            // Do not clear elements with data-no-clear attribute
            if(element.dataset.noClear) {
                continue;
            }

            element.disabled = true;
        }

        form.submit();
    }
}