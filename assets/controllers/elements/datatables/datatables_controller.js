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

//Styles
import 'datatables.net-bs5/css/dataTables.bootstrap5.css'
import 'datatables.net-buttons-bs5/css/buttons.bootstrap5.css'
import 'datatables.net-fixedheader-bs5/css/fixedHeader.bootstrap5.css'
import 'datatables.net-responsive-bs5/css/responsive.bootstrap5.css';
import 'datatables.net-select-bs5/css/select.bootstrap5.css';

//JS
import 'datatables.net-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/buttons.colVis.js';
import 'datatables.net-fixedheader-bs5';
import 'datatables.net-select-bs5';
import 'datatables.net-colreorder-bs5';
import 'datatables.net-responsive-bs5';
import '../../../js/lib/datatables';

const EVENT_DT_LOADED = 'dt:loaded';

export default class extends Controller {

    static targets = ['dt'];

    static values = {
        stateSaveTag: String
    };

    /** The datatable instance associated with this controller instance */
    _dt;

    getStateSaveKey() {
        let key = 'dt_state_'

        if(this.stateSaveTagValue) { //If a tag is provided, use it to store the state
            key += this.stateSaveTagValue;
        } else { //Otherwise generate one from the current url
            key += window.location.pathname;
        }

        return key;
    }

    stateSaveCallback(settings, data) {
        localStorage.setItem( this.getStateSaveKey(), JSON.stringify(data) );
    }

    stateLoadCallback(settings) {
        return JSON.parse( localStorage.getItem(this.getStateSaveKey()) );
    }

    connect() {
        //$($.fn.DataTable.tables()).DataTable().fixedHeader.disable();
        //$($.fn.DataTable.tables()).DataTable().destroy();

        const settings = JSON.parse(this.element.dataset.dtSettings);
        if(!settings) {
            throw new Error("No settings provided for datatable!");
        }

        //Add url info, as the one available in the history is not enough, as Turbo may have not changed it yet
        settings.url = this.element.dataset.dtUrl;


        let options = {
            colReorder: true,
            responsive: true,
            fixedHeader: {
                header: $(window).width() >= 768, //Only enable fixedHeaders on devices with big screen. Fixes scrolling issues on smartphones.
                headerOffset: $("#navbar").height()
            },
            buttons: [{
                "extend": 'colvis',
                'className': 'mr-2 btn-light',
                'columns': ':not(.no-colvis)',
                "text": "<i class='fa fa-cog'></i>"
            }],


            rowCallback: this._rowCallback.bind(this),
            stateSave: true,
            stateSaveCallback: this.stateSaveCallback.bind(this),
            stateLoadCallback: this.stateLoadCallback.bind(this),
        };

        if(this.isSelectable()) {
            options.select = {
                style:    'multi+shift',
                selector: 'td.select-checkbox'
            };
        }

        //@ts-ignore
        const promise = $(this.dtTarget).initDataTables(settings, options)
            //Register error handler
            .catch(err => {
                console.error("Error initializing datatables: " + err);
            });

        //Dispatch an event to let others know that the datatables has been loaded
        promise.then((dt) => {
            const event = new CustomEvent(EVENT_DT_LOADED, {bubbles: true});
            this.element.dispatchEvent(event);

            this._dt = dt;
        });

        //Register event handlers
        promise.then((dt) => {
            dt.on('select.dt deselect.dt', this._onSelectionChange.bind(this));
        });

        //Allow to further configure the datatable
        promise.then(this._afterLoaded.bind(this));


        console.debug('Datatables inited.');
    }

    _rowCallback(row, data, index) {
        //Empty by default but can be overridden by child classes
    }

    _onSelectionChange(e, dt, items ) {
        //Empty by default but can be overridden by child classes
        alert("Test");
    }

    _afterLoaded(dt) {
        //Empty by default but can be overridden by child classes
    }

    /**
     * Check if this datatable has selection feature enabled
     */
    isSelectable()
    {
        return this.element.dataset.select ?? false;
    }

}