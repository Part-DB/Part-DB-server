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
import TomSelect from "tom-select";
import katex from "katex";
import "katex/dist/katex.css";

/* stimulusFetch: 'lazy' */
export default class extends Controller
{
    static values = {
        url: String,
    }

    static targets = ["name", "symbol", "unit"]

    onItemAdd(value, item) {
        //Retrieve the unit and symbol from the item
        const symbol = item.dataset.symbol;
        const unit = item.dataset.unit;

        if (this.symbolTarget && symbol !== undefined) {
            this.symbolTarget.value = symbol;
            //Trigger input event to update the preview
            this.symbolTarget.dispatchEvent(new Event('input'));
        }
        if (this.unitTarget && unit !== undefined) {
            this.unitTarget.value = unit;
            //Trigger input event to update the preview
            this.unitTarget.dispatchEvent(new Event('input'));
        }
    }

    connect() {
        const settings = {
            plugins: {
                clear_button:{}
            },
            persistent: false,
            maxItems: 1,
            //This a an ugly solution to disable the delimiter parsing of the TomSelect plugin
            delimiter: 'VERY_L0NG_D€LIMITER_WHICH_WILL_NEVER_BE_ENCOUNTERED_IN_A_STRING',
            createOnBlur: true,
            create: true,
            searchField: "name",
            //labelField: "name",
            valueField: "name",
            onItemAdd: this.onItemAdd.bind(this),
            render: {
                option: (data, escape) => {
                    let tmp = '<div>'
                        + '<span>' + escape(data.name) + '</span><br>';

                    if (data.symbol) {
                        tmp += '<span>' + katex.renderToString(data.symbol) + '</span>'
                    }
                    if (data.unit) {
                        tmp += '<span class="ms-2">' + katex.renderToString('[' + data.unit + ']') + '</span>'
                    }


                    //+ '<span class="text-muted">' + escape(data.unit) + '</span>'
                    tmp += '</div>';

                    return tmp;
                },
                item: (data, escape) => {
                    //We use the item to transfert data to the onItemAdd function using data attributes
                    const element = document.createElement('div');
                    element.innerText = data.name;
                    if(data.unit !== undefined) {
                        element.dataset.unit = data.unit;
                    }
                    if (data.symbol !== undefined) {
                        element.dataset.symbol = data.symbol;
                    }

                    return element.outerHTML;
                }
            }
        };

        if(this.urlValue) {
            const base_url = this.urlValue;
            settings.load = (query, callback) => {
                const url = base_url.replace('__QUERY__', encodeURIComponent(query));

                fetch(url)
                    .then(response => response.json())
                    .then(json => {
                        //const data = json.map(x => {return {"value": x, "text": x}});
                        callback(json);
                    }).catch(()=>{
                    callback();
                });
            }
        }

        this._tomSelect = new TomSelect(this.nameTarget, settings);
    }
}