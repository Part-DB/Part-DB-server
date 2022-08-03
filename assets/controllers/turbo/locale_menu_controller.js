import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        const menu = document.getElementById('locale-select-menu');
        menu.innerHTML = this.element.innerHTML;
    }
}