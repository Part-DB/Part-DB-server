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

import TomSelect_click_to_edit from '../../tomselect/click_to_edit/click_to_edit'
import TomSelect_autoselect_typed from '../../tomselect/autoselect_typed/autoselect_typed'

TomSelect.define('click_to_edit', TomSelect_click_to_edit)
TomSelect.define('autoselect_typed', TomSelect_autoselect_typed)

export default class extends Controller {
    _tomSelect;

    _platformSelector;

    connect() {

        let dropdownParent = "body";
        if (this.element.closest('.modal')) {
            dropdownParent = null
        }

        //Try to find the platform selector
        const platformSelector = document.querySelector("select[data-platform-selector-label='" + this.element.dataset.platformSelector + "']");
        //Clear tomselect options, if the platform selector changes
        if (platformSelector) {
            this.platformSelector = platformSelector;
            platformSelector.addEventListener('change', () => {
                //Force reload of options by clearing the cache and options of TomSelect and triggering a search with an empty string
                this._tomSelect.clearOptions();
                this._tomSelect.clearCache();
                this._tomSelect.load('');
            });
        }

        let settings = {
            persistent: false,
            create: true,
            maxItems: 1,
            preload: 'focus',
            createOnBlur: true,
            selectOnTab: true,
            clearAfterSelect: true,
            shouldLoad: ((query) => true),
            maxOptions: null,
            //This a an ugly solution to disable the delimiter parsing of the TomSelect plugin
            delimiter: 'VERY_L0NG_D€LIMITER_WHICH_WILL_NEVER_BE_ENCOUNTERED_IN_A_STRING',
            dropdownParent: dropdownParent,
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
            },
            plugins: {
                'autoselect_typed': {},
                'click_to_edit': {},
                'clear_button': {},
                "restore_on_backspace": {}
            }
        };

        if(this.element.dataset.urlTemplate) {
            const base_url = this.element.dataset.urlTemplate;
            settings.searchField = "label";
            settings.sortField = "label";
            settings.valueField = "label";
            settings.load = (query, callback) => {


                if (!this.platformSelector) {
                    console.error("Platform selector not found for AI model autocomplete");
                    callback();
                    return;
                }

                //Platform is the selected option
                const platform = this.platformSelector.value;
                if (!platform) {
                    callback();
                    return;
                }

                const self = this;

                //Only fetch each platform once
                if(self.platformLoaded === platform) {
                    callback();
                }


                const url = base_url.replace('__PLATFORM__', encodeURIComponent(platform));

                fetch(url)
                    .then(response => response.json())
                    .then(json => {

                        self.platformLoaded = platform;

                        var data = [];

                        for (const name in json) {
                            data.push({
                                "label": name,
                                "capabilities": json[name].capabilities,
                            });
                        }

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


