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
        const self = this;
        let settings = {
            persistent: false,
            create: true,
            maxItems: 5,
            createOnBlur: true,
            selectOnTab: true,
            //This a an ugly solution to disable the delimiter parsing of the TomSelect plugin
            delimiter: ' ',
            render: {
                item: function item(data, escape) {
                    var tpl = document.createElement('template');
                    tpl.innerHTML = '<span>|' + escape(data.label) + '|</span>';
                    var thing = tpl.content.firstChild;

                    thing.addEventListener('click', evt => {
                            if (!self._tomSelect.isFocused) {
                                //return;
                            }
                            if (self._tomSelect.isLocked) return;
                            var val = self._tomSelect.inputValue()
                            if (self._tomSelect.options[val]) {
                                self._tomSelect.addItem(val)
                            } else if (self._tomSelect.settings.create) {
                                self._tomSelect.createItem();
                            }
                            self._tomSelect.setTextboxValue()
                            self._tomSelect.focus();
                            self._tomSelect.removeItem(thing);
                        }
                    );

                    return thing;
                },
                option: function option(data, escape) {
                    if (data.image) {
                        return "<div class='row m-0'><div class='col-2 pl-0 pr-1'><img class='typeahead-image' src='" + data.image + "'/></div><div class='col-10'>" + data.label + "</div></div>";
                    }
                    return '<div>' + escape(data.label) + '</div>';
                }
            },
            onInitialize: function () {
            },
            onItemRemove: function (value) {
                console.log(value)
                console.log(self._tomSelect.options)
                self._tomSelect.setTextboxValue(value);
                if (self._tomSelect.control_input.value.trim() === '') {
                    var option = self.options[value];
                    if (option) {
                    }
                }
            },
            plugins: {//'restore_on_backspace': {},
                //'remove_button': {}
            }
        };
        if (this.element.dataset.autocomplete) {
            var base_url = this.element.dataset.autocomplete;
            settings.searchField = "label";
            settings.sortField = "label";
            settings.valueField = "label";
            settings.load = function (query, callback) {
                if (query.length < 2) {
                    callback();
                    return;
                }
                var url = base_url.replace('__QUERY__', encodeURIComponent(query));
                fetch(url).then(function (response) {
                    return response.json();
                }).then(function (json) {
                    var data = json.map(function (x) {
                        return {
                            "label": x.name,
                            "image": x.image
                        };
                    });
                    callback(data);
                })["catch"](function () {
                    callback();
                });
            }
            ;
        }

        this._tomSelect = new tom_select__WEBPACK_IMPORTED_MODULE_31__["default"](this.element, settings);
        this._tomSelect.hook("before", "onBlur", function () {
            var val = self._tomSelect.inputValue()
            if (!self._tomSelect.isLocked && self._tomSelect.options[val]) {
                self._tomSelect.addItem(val)
                self._tomSelect.setTextboxValue()
            }
        });
        this._tomSelect.hook("before", "onKeyPress", function (e) {
            var character = String.fromCharCode(e.keyCode || e.which);
            if (!self._tomSelect.isLocked && self._tomSelect.settings.mode === 'multi' && character === self._tomSelect.settings.delimiter) {
                var val = self._tomSelect.inputValue()
                if (self._tomSelect.options[val]) {
                    self._tomSelect.addItem(val)
                    self._tomSelect.setTextboxValue()
                }
            }
        });
    }
    disconnect() {
        super.disconnect();
        //Destroy the TomSelect instance
        this._tomSelect.destroy();
    }

}
