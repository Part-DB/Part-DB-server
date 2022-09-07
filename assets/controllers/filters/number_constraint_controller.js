import {Controller} from "@hotwired/stimulus";

export default class extends Controller {

    static targets = ["operator", "thingsToHide"];

    connect() {
        this.update();
    }

    /**
     * Updates the visibility state of the value2 input, based on the operator selection.
     */
    update()
    {
        const two_element_values = [
            "BETWEEN",
            'RANGE_IN_RANGE',
            'RANGE_INTERSECT_RANGE'
        ];

        for (const thingToHide of this.thingsToHideTargets) {
            thingToHide.classList.toggle("d-none",  !two_element_values.includes(this.operatorTarget.value));
        }
    }
}