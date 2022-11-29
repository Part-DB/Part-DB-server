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
import '../../css/tom-select_extensions.css';
import TomSelect from "tom-select";

export default class extends Controller {
    _tomSelect;

    connect() {
        let settings = {
            plugins: {
                remove_button:{
                }
            },
            persistent: false,
            createOnBlur: true,
            create: true,
        };

        if(this.element.dataset.autocomplete) {
            const base_url = this.element.dataset.autocomplete;
            settings.load = (query, callback) => {
                if(query.length < 2){
                    callback();
                    return;
                }
                const url = base_url.replace('__QUERY__', encodeURIComponent(query));

                fetch(url)
                    .then(response => response.json())
                    .then(json => {
                        const data = json.map(x => {return {"value": x, "text": x}});
                        callback(data);
                    }).catch(()=>{
                    callback();
                });
            }
        }

        this._tomSelect = new TomSelect(this.element, settings);

        /*if(this.element.dataset.autocomplete) {
            const engine = new Bloodhound({
                //@ts-ignore
                datumTokenizer: Bloodhound.tokenizers.obj.whitespace(''),
                //@ts-ignore
                queryTokenizer: Bloodhound.tokenizers.obj.whitespace(''),
                remote: {
                    url: this.element.dataset.autocomplete,
                    wildcard: 'QUERY'
                }
            });

            $(this.element).tagsinput({
                typeaheadjs: {
                    name: 'tags',
                    source: engine.ttAdapter()
                }
            });
        } else { // Init tagsinput without typeahead
            $(this.element).tagsinput();
        }*/


    }
}