/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

import {Controller} from "@hotwired/stimulus";

import * as bootbox from "bootbox";
import "../../css/components/bootbox_extensions.css";

export default class extends Controller
{
    connect()
    {
        this._confirmed = false;
    }

    click(event) {
        //If a user has not already confirmed the deletion, just let turbo do its work
        if(this._confirmed) {
            this._confirmed = false;
            return;
        }

        event.preventDefault();

        const message = this.element.dataset.deleteMessage;
        const title = this.element.dataset.deleteTitle;

        const that = this;

        const confirm = bootbox.confirm({
            message: message, title: title, callback: function (result) {
                //If the dialog was confirmed, then submit the form.
                if (result) {
                    that._confirmed = true;
                    event.target.click();
                } else {
                    that._confirmed = false;
                }
            }
        });
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