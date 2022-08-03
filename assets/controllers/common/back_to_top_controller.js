import {Controller} from "@hotwired/stimulus";
import {Tooltip} from "bootstrap";

export default class extends Controller {

    connect() {
        window.addEventListener('scroll', this._onscroll.bind(this));
    }

    _onscroll() {
        const button = this.element;

        if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
            button.style.display = "block";
        } else {
            button.style.display = "none";
        }
    }

    backToTop() {
        //Hide button tooltip to prevent ugly tooltip on scroll
        Tooltip.getInstance(this.element)?.hide();

        document.body.scrollTop = 0; // For Safari
        document.documentElement.scrollTop = 0; // For Chrome, Firefox, IE and Opera
    }
}