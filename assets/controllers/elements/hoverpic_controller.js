import {Controller} from "@hotwired/stimulus";
import {Popover} from "bootstrap";

export default class extends Controller {
    connect() {
        const thumbnail_url = this.element.dataset.thumbnail;

        this._popover = Popover.getOrCreateInstance(this.element, {
            html: true,
            trigger: 'hover',
            placement: 'right',
            container: 'body',
            content: function () {
                return '<img class="img-fluid" src="' + thumbnail_url + '" />';
            }
        });

        this._popover.hide();
    }
}