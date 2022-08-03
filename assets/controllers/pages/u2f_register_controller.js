import {Controller} from "@hotwired/stimulus";

export default class extends Controller
{
    connect() {
        this.element.onclick = function() {
            window.u2fauth.register();
        }
    }
}
