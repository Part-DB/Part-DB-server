/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

export default class extends Controller {

    static targets = [ "display", "select" ]

    connect()
    {
        this.update();
        this.selectTarget.addEventListener('change', this.update.bind(this));
    }

    update()
    {
        //If the select value is 0, then we show the input field
        if( this.selectTarget.value === '0')
        {
            this.displayTarget.classList.remove('d-none');
        }
        else
        {
            this.displayTarget.classList.add('d-none');
        }
    }
}