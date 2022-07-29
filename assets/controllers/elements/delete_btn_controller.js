import {Controller} from "@hotwired/stimulus";

import * as bootbox from "bootbox";

export default class extends Controller
{
    connect()
    {
        this._confirmed = false;
    }

    submit(event) {
        //If a user has not already confirmed the deletion, just let turbo do its work
        if(this._confirmed) {
            this._confirmed = false;
            return;
        }

        //Prevent turbo from doing its work
        event.preventDefault();

        const message = this.element.dataset.deleteMessage;
        const title = this.element.dataset.deleteTitle;

        const form = this.element;
        const that = this;

        //Create a clone of the event with the same submitter, so we can redispatch it if needed
        //We need to do this that way, as we need the submitter info, just calling form.submit() would not work
        this._our_event = new SubmitEvent('submit', {
            submitter: event.submitter,
            bubbles: true, //This line is important, otherwise Turbo will not receive the event
        });

        const confirm = bootbox.confirm({
            message: message, title: title, callback: function (result) {
                //If the dialog was confirmed, then submit the form.
                if (result) {
                    that._confirmed = true;
                    form.dispatchEvent(that._our_event);
                } else {
                    that._confirmed = false;
                }
            }
        });
    }
}