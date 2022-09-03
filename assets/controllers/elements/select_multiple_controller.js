import {Controller} from "@hotwired/stimulus";
import TomSelect from "tom-select";

export default class extends Controller {
    _tomSelect;

    connect() {
        this._tomSelect = new TomSelect(this.element, {
            maxItems: 1000,
            allowEmptyOption: true,
            plugins: ['remove_button'],
        });
    }

}