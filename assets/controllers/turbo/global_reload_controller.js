import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        //If we encounter an element with global reload controller, then reload the whole page
        window.location.href = window.location.href;
    }
}