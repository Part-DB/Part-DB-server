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
import {default as TreeController} from "./tree_controller";

export default class extends TreeController {
    static targets = [ "tree", 'sourceText' ];

    _storage_key;

    _lastUpdate;

    connect() {
        //Check if the tree is already initialized, if so then skip initialization (useful when going back) to in history using Turbo
        if(this._isInitialized()) {
            return;
        }

        const default_mode = this.element.dataset.defaultMode;

        this._storage_key = 'tree_' + this.element.id;

        //Check if we have a saved mode
        const stored_mode = localStorage.getItem(this._storage_key);

        //Use stored mode if possible, otherwise use default
        if(stored_mode) {
            try {
                this.setMode(stored_mode);
            } catch (e) {
                console.error(e);
                //If an error happenes, use the default mode
                this.setMode(default_mode);
            }
        } else {
            this.setMode(default_mode);
        }

        //Register an event listener which checks if the tree needs to be updated
        document.addEventListener('turbo:render', this.doUpdateIfNeeded.bind(this));
    }

    doUpdateIfNeeded()
    {
        const info_element = document.getElementById('sidebar-last-time-updated');
        const date_str = info_element.dataset.lastUpdate;
        const server_last_update = new Date(date_str);

        if(this._lastUpdate < server_last_update) {
            console.log("Sidebar tree is outdated, reloading (last update: " + this._lastUpdate + ", server update: " + server_last_update + ")");
            this._lastUpdate = new Date();



            this.reinitTree();
        }
    }

    setMode(mode) {
        //Find the button for this mode
        const modeButton = this.element.querySelector(`[data-mode="${mode}"]`);
        if(!modeButton) {
            throw new Error(`Could not find button for mode ${mode}`);
        }

        //Get the url and text from the button
        const url = modeButton.dataset.url;
        const text = modeButton.dataset.text;

        this.sourceTextTarget.innerText = text;

        this.setURL(url);

        //Update the last update time
        this._lastUpdate = new Date();
    }

    changeDataSource(event)
    {
        const mode = event.params.mode ?? event.target.dataset.mode;
        const url = event.params.url ?? event.target.dataset.url;
        const text = event.params.text ?? event.target.dataset.text;

        this.sourceTextTarget.innerText = text;

        this.setURL(url);

        //Save the mode in local storage
        localStorage.setItem(this._storage_key, mode);

        this._lastUpdate = new Date();
    }
}
