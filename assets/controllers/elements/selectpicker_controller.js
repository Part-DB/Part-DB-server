const bootstrap = window.bootstrap = require('bootstrap'); // without this bootstrap-select crashes with `undefined bootstrap`
require('bootstrap-select/js/bootstrap-select'); // we have to manually require the working js file

import {Controller} from "@hotwired/stimulus";
//import "bootstrap-select/js/bootstrap-select";
import 'bootstrap-select/sass/bootstrap-select.scss';

export default class extends Controller {
    connect() {
        $(this.element).selectpicker({
            dropdownAlignRight: 'auto',
            container: '#content',
        });
    }
}