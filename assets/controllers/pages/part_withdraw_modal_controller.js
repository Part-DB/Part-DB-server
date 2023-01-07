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

        //Set the title
        const titleElement = this.element.querySelector('.modal-title');
        switch (action) {
            case 'withdraw':
                titleElement.innerText = titleElement.getAttribute('data-withdraw');
                break;
            case 'add':
                titleElement.innerText = titleElement.getAttribute('data-add');
                break;
            case 'move':
                titleElement.innerText = titleElement.getAttribute('data-move');
                break;
        }

        //Hide the move to lot select, if the action is not move (and unhide it, if it is)
        const moveToLotSelect = this.element.querySelector('#withdraw-modal-move-to');
        if (action === 'move') {
            moveToLotSelect.classList.remove('d-none');
        } else {
            moveToLotSelect.classList.add('d-none');
        }

        //First unhide all move to lot options and then hide the currently selected lot
        const moveToLotOptions = moveToLotSelect.querySelectorAll('input[type="radio"]');
        moveToLotOptions.forEach(option => option.parentElement.classList.remove('d-none'));
        moveToLotOptions.forEach(option => {
            if (option.getAttribute('value') === lotID) {
                option.parentElement.classList.add('d-none');
                option.selected = false;
            }
        });

        //For adding parts there is no limit on the amount to add
        if (action == 'add') {
            amountInput.removeAttribute('max');
        } else { //Every other action is limited to the amount of parts in the lot
            amountInput.setAttribute('max', lotAmount);
        }
    }
}