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
import katex from "katex";
import "katex/dist/katex.css";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ["input", "preview"];

    static values = {
        unit: {type: Boolean, default: false} //Render as upstanding (non-italic) text, useful for units
    }

    updatePreview()
    {
        let value = "";
        if (this.unitValue) {
            //Escape percentage signs
            value = this.inputTarget.value.replace(/%/g, '\\%');

            value = "\\mathrm{" + value + "}";
        } else {
            value = this.inputTarget.value;
        }

        katex.render(value, this.previewTarget, {
            throwOnError: false,
        });
    }

    connect()
    {
        this.updatePreview();
        this.inputTarget.addEventListener('input', this.updatePreview.bind(this));
    }
}