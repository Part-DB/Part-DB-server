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

    submit(event) {
        //If a user has not already confirmed the deletion, just let turbo do its work
        if (this._confirmed) {
            this._confirmed = false;
            return;
        }

        //Prevent turbo from doing its work
        event.preventDefault();
        event.stopPropagation();

        const message = this.element.dataset.deleteMessage;
        const title = this.element.dataset.deleteTitle;

        const form = this.element;
        const that = this;

        const confirm = bootbox.confirm({
            message: message, title: title, callback: function (result) {
                //If the dialog was confirmed, then submit the form.
                if (result) {
                    //Set a flag to prevent the dialog from popping up again and allowing turbo to submit the form
                    that._confirmed = true;

                    //Create a submit button in the form and click it to submit the form
                    //Before a submit event was dispatched, but this caused weird issues on Firefox causing the delete request being posted twice (and the second time was returning 404). See https://github.com/Part-DB/Part-DB-server/issues/273
                    const submit_btn = document.createElement('button');
                    submit_btn.type = 'submit';
                    submit_btn.style.display = 'none';
                    form.appendChild(submit_btn);
                    submit_btn.click();
                } else {
                    that._confirmed = false;
                }
            }
        });
    }
}