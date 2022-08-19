import {Controller} from "@hotwired/stimulus";

export default class extends Controller {

    static targets = ["operator", "thingsToHide"];

    connect() {
        this.update();
        debugger;
    }

    /**
     * Updates the visibility state of the value2 input, based on the operator selection.
     */
    update()
    {
        for (const thingToHide of this.thingsToHideTargets) {
            thingToHide.classList.toggle("d-none", this.operatorTarget.value !== "BETWEEN");
        }
    }
}