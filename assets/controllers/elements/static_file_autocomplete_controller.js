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

import "tom-select/dist/css/tom-select.bootstrap5.css";
import '../../css/components/tom-select_extensions.css';
import TomSelect from "tom-select";

/**
 * This is the frontend controller for StaticFileAutocompleteType form element.
 * Basically it loads a text file from the given url (via data-url) and uses it as a source for the autocomplete.
 * The file is just a list of strings, one per line, which will be used as the autocomplete options.
 * Lines starting with # will be ignored.
 */
export default class extends Controller {
    _tomSelect;

    connect() {

        let settings = {
            persistent: false,
            create: true,
            maxItems: 1,
            maxOptions: 100,
            createOnBlur: true,
            selectOnTab: true,
            valueField: 'text',
            searchField: 'text',
            orderField: 'text',

            //This a an ugly solution to disable the delimiter parsing of the TomSelect plugin
            delimiter: 'VERY_L0NG_D€LIMITER_WHICH_WILL_NEVER_BE_ENCOUNTERED_IN_A_STRING'
        };

        if (this.element.dataset.url) {
            const url = this.element.dataset.url;
            settings.load = (query, callback) => {
                const self = this;
                if (self.loading > 1) {
                    callback();
                    return;
                }

                fetch(url)
                    .then(response => response.text())
                    .then(text => {
                        // Convert the text file to array
                        let lines = text.split("\n");
                        //Remove all lines beginning with #
                        lines = lines.filter(x => !x.startsWith("#"));

                        //Convert the array to an object, where each line is in the text field
                        lines = lines.map(x => {
                            return {text: x};
                        });


                        //Unset the load function to prevent endless recursion
                        self._tomSelect.settings.load = null;

                        callback(lines);
                    }).catch(() => {
                    callback();
                });
            };
        }

        this._tomSelect = new TomSelect(this.element, settings);
    }

    disconnect() {
        super.disconnect();
        //Destroy the TomSelect instance
        this._tomSelect.destroy();
    }

}
