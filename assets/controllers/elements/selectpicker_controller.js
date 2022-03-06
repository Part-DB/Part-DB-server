import {Controller} from "@hotwired/stimulus";
import "bootstrap-select";
import 'bootstrap-select/dist/css/bootstrap-select.css'

export default class extends Controller {
    connect() {
        $(this.element).selectpicker({
            dropdownAlignRight: 'auto',
            container: '#content',
        });
    }
}