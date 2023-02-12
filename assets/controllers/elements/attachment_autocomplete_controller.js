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

import "tom-select/dist/css/tom-select.bootstrap5.css";
import '../../css/components/tom-select_extensions.css';
import TomSelect from "tom-select";

export default class extends Controller {
    _tomSelect;

    connect() {

        let settings = {
            persistent: false,
            create: true,
            maxItems: 1,
            createOnBlur: true,
            selectOnTab: true,
            //This a an ugly solution to disable the delimiter parsing of the TomSelect plugin
            delimiter: 'VERY_L0NG_D€LIMITER_WHICH_WILL_NEVER_BE_ENCOUNTERED_IN_A_STRING',
            render: {
                item: (data, escape) => {
                    return '<span>' + escape(data.label) + '</span>';
                },
                option: (data, escape) => {
                    if (data.image) {
                        return "<div class='row m-0'><div class='col-2 pl-0 pr-1'><img class='typeahead-image' src='" + data.image + "'/></div><div class='col-10'>" + data.label + "</div></div>"
                    }
                    return '<div>' + escape(data.label) + '</div>';
                }
            }
        };

        if(this.element.dataset.autocomplete) {
            const base_url = this.element.dataset.autocomplete;
            settings.searchField = "label";
            settings.sortField = "label";
            settings.valueField = "label";
            settings.load = (query, callback) => {
                if(query.length < 2){
                    callback();
                    return;
                }
                const url = base_url.replace('__QUERY__', encodeURIComponent(query));

                fetch(url)
                    .then(response => response.json())
                    .then(json => {
                        const data = json.map(x => {
                            return {
                                "label": x.name,
                                "image": x.image,
                            }
                        });
                        callback(data);
                    }).catch(()=>{
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
