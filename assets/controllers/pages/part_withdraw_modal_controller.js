import {Controller} from "@hotwired/stimulus";
import {Modal} from "bootstrap";

export default class extends Controller
{
    connect() {


        this.element.addEventListener('show.bs.modal', event => this._handleModalOpen(event));

        //Register an event to remove the backdrop, when the form is submitted
        const form = this.element.querySelector('form');
        form.addEventListener('submit', event => {
            //Remove the backdrop
            document.querySelector('.modal-backdrop').remove();
        });
    }

    _handleModalOpen(event) {
        // Button that triggered the modal
        const button = event.relatedTarget;

        const amountInput = this.element.querySelector('input[name="amount"]');

        // Extract info from button attributes
        const action = button.getAttribute('data-action');
        const lotID = button.getAttribute('data-lot-id');
        const lotAmount = button.getAttribute('data-lot-amount');

        //Set the action and lotID inputs in the form
        this.element.querySelector('input[name="action"]').setAttribute('value', action);
        this.element.querySelector('input[name="lot_id"]').setAttribute('value', lotID);

        //For adding parts there is no limit on the amount to add
        if (action == 'add') {
            amountInput.removeAttribute('max');
        } else { //Every other action is limited to the amount of parts in the lot
            amountInput.setAttribute('max', lotAmount);
        }
    }
}