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

    static values = {
        message: String,
        title: String
    }



    connect()
    {
        this._confirmed = false;

        this.element.addEventListener('click', this._onClick.bind(this));
    }

    _onClick(event)
    {

        //If a user has not already confirmed the deletion, just let turbo do its work
        if (this._confirmed) {
            this._confirmed = false;
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const that = this;

        bootbox.confirm({
            title: this.titleValue,
            message: this.messageValue,
            callback: (result) => {
                if (result) {
                    //Set a flag to prevent the dialog from popping up again and allowing turbo to submit the form
                    that._confirmed = true;

                    //Click the link
                    that.element.click();
                } else {
                    that._confirmed = false;
                }
            }
        });
    }
}