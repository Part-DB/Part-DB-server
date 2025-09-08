import { Controller } from "@hotwired/stimulus";

export default class extends Controller {

    static values = {
        classes: Array
    };

    connect() {
        this.readableCheckbox = this.element.querySelector("#readable");

        if (!this.readableCheckbox) {
            return;
        }

        // Apply the initial visibility state based on the checkbox being checked or not
        this.toggleContainers(this.readableCheckbox.checked);

        // Add a change event listener to the 'readable' checkbox
        this.readableCheckbox.addEventListener("change", (event) => {
            // Toggle container visibility when the checkbox value changes
            this.toggleContainers(event.target.checked);
        });
    }

    /**
     * Toggles the visibility of containers based on the checkbox state.
     * Hides specified containers if the checkbox is checked and shows them otherwise.
     *
     * @param {boolean} isChecked - The current state of the checkbox:
     *                              true if checked (hide elements), false if unchecked (show them).
     */
    toggleContainers(isChecked) {
        if (!Array.isArray(this.classesValue) || this.classesValue.length === 0) {
            return;
        }

        this.classesValue.forEach((cssClass) => {
            const elements = document.querySelectorAll(`.${cssClass}`);

            if (!elements.length) {
                return;
            }

            // Update the visibility for each selected element
            elements.forEach((element) => {
                // If the checkbox is checked, hide the container; otherwise, show it
                element.style.display = isChecked ? "none" : "";
            });
        });
    }
}