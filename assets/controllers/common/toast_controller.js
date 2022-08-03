import { Controller } from '@hotwired/stimulus';
import { Toast } from 'bootstrap';

export default class extends Controller {
    connect() {
        //Move all toasts from the page into our toast container and show them

        const toastContainer = document.getElementById('toast-container');
        if (this.element.parentNode !== toastContainer) {
            toastContainer.appendChild(this.element);
            return;
        }

        const toast = new Toast(this.element);
        toast.show();
    }
}