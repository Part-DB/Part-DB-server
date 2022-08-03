import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        //If we encounter an element with this, then change the title of our document according to data-title
        this.changeTitle(this.element.dataset.title);
    }

    changeTitle(title) {
        document.title = title;
    }
}