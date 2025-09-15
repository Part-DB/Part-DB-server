import { Controller } from "@hotwired/stimulus";

export default class extends Controller {

    static values = {
        classes: Array
    };

    connect() {
        this.displayCheckbox = this.element.querySelector("#display");
        this.displaySelect = this.element.querySelector("select#display");

        if (this.displayCheckbox) {
            this.toggleContainers(this.displayCheckbox.checked);

            this.displayCheckbox.addEventListener("change", (event) => {
                this.toggleContainers(event.target.checked);
            });
        }

        if (this.displaySelect) {
            this.toggleContainers(this.hasDisplaySelectValue());

            this.displaySelect.addEventListener("change", () => {
                this.toggleContainers(this.hasDisplaySelectValue());
            });
        }

    }

    /**
     * Check whether a value was selected in the selectbox
     * @returns {boolean} True when a value has not been selected that is not empty
     */
    hasDisplaySelectValue() {
        return this.displaySelect && this.displaySelect.value !== "";
    }

    /**
     * Hides specified containers if the state is active (checkbox checked or select with value).
     *
     * @param {boolean} isActive - True when the checkbox is activated or the selectbox has a value.
     */
    toggleContainers(isActive) {
        if (!Array.isArray(this.classesValue) || this.classesValue.length === 0) {
            return;
        }

        this.classesValue.forEach((cssClass) => {
            const elements = document.querySelectorAll(`.${cssClass}`);

            if (!elements.length) {
                return;
            }

            elements.forEach((element) => {
                element.style.display = isActive ? "none" : "";
            });
        });
    }

}
