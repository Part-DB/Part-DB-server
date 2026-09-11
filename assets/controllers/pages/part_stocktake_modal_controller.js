import {Controller} from "@hotwired/stimulus";
import {Modal} from "bootstrap";

export default class extends Controller
{
    connect() {
        this.element.addEventListener('show.bs.modal', event => this._handleModalOpen(event));
    }

    _handleModalOpen(event) {
        // Button that triggered the modal
        const button = event.relatedTarget;

        const amountInput = this.element.querySelector('input[name="amount"]');

        // Extract info from button attributes
        const lotID = button.getAttribute('data-lot-id');
        const lotAmount = button.getAttribute('data-lot-amount');

        //Find the expected amount field and set the value to the lot amount
        const expectedAmountInput = this.element.querySelector('#stocktake-modal-expected-amount');
        expectedAmountInput.textContent = lotAmount;

        //Set the action and lotID inputs in the form
        this.element.querySelector('input[name="lot_id"]').setAttribute('value', lotID);
    }
}
