import {Controller} from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["input"];

    connect() {
        if(!this.inputTarget) {
            throw new Error("Target input not found");
        }
    }

    load(event) {
        //Use the data-value attribute to load the value of our target input
        this.inputTarget.value = event.target.dataset.value;
    }
}