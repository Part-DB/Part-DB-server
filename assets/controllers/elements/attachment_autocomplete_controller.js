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
import TomSelect_form_reset_handler from '../../tomselect/form_reset_handler/form_reset_handler'

TomSelect.define('click_to_edit', TomSelect_click_to_edit)
TomSelect.define('autoselect_typed', TomSelect_autoselect_typed)
TomSelect.define('form_reset_handler', TomSelect_form_reset_handler)

export default class extends Controller {
    _tomSelect;

    connect() {

        let dropdownParent = "body";
        if (this.element.closest('.modal')) {
            dropdownParent = null
        }

        let settings = {
            persistent: false,
            create: true,
            maxItems: 1,
            createOnBlur: true,
            selectOnTab: true,
            clearAfterSelect: true,
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
                'restore_on_backspace': {},
                'form_reset_handler': {}
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

        //Allow to paste an image from the clipboard into the URL field, which is then used as file for this attachment
        this._onPaste = this.onPaste.bind(this);
        this._tomSelect.wrapper.addEventListener('paste', this._onPaste);
    }

    /**
     * If the clipboard contains a file (e.g. a screenshot or a file copied in the file manager), assign it to the file
     * input of the attachment row this URL field belongs to. Normal text pastes are not affected.
     * @param {ClipboardEvent} event
     */
    onPaste(event) {
        const files = event.clipboardData?.files ?? [];
        if (files.length === 0) {
            return;
        }

        const row = this.element.closest('[data-controller~="elements--attachmenttype-change"]');
        const fileInput = row?.querySelector("input[type='file']");
        if (!fileInput) {
            return;
        }

        event.preventDefault();

        //Pasted images are usually all named "image.png", so give them a more meaningful name
        let file = files[0];
        if (file.type.startsWith('image/') && (file.name === '' || file.name === 'image.png')) {
            const extension = file.type.split('/')[1] || 'png';
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            file = new File([file], `pasted_image_${timestamp}.${extension}`, {type: file.type});
        }

        //We can not directly assign the file to the input, so we have to create a new DataTransfer object
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fileInput.files = dataTransfer.files;
        fileInput.dispatchEvent(new Event('change', {bubbles: true}));
    }

    disconnect() {
        super.disconnect();
        this._tomSelect.wrapper.removeEventListener('paste', this._onPaste);
        //Destroy the TomSelect instance
        this._tomSelect.destroy();
    }

}
