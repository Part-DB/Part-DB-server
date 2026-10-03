/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * Shown on the info page of an Amazon part, which was not looked up at Canopy yet (and only if the "fetch on view"
 * setting of the Canopy provider is enabled). It asks the server to retrieve the data, shows that this is in
 * progress, and reloads the page once the data is there.
 */
export default class extends Controller {
    static targets = ["spinner", "done", "failed", "message"];

    static values = {
        url: String,
        token: String,
        messages: Object,
    };

    /** How often to ask again, while another request is retrieving the data of this part */
    static MAX_ATTEMPTS = 20;
    static RETRY_DELAY = 3000;

    connect() {
        //Turbo shows a cached copy of the page while it loads the real one, which will start the request itself
        if (document.documentElement.hasAttribute('data-turbo-preview')) {
            return;
        }

        this._attempts = 0;
        this._waited = false;
        this._fetch();
    }

    disconnect() {
        clearTimeout(this._timeout);
        this._timeout = null;
        this._disconnected = true;
    }

    async _fetch() {
        this._attempts++;

        let status = 'error';
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: new URLSearchParams({_token: this.tokenValue}),
            });
            if (response.ok) {
                status = (await response.json()).status;
            }
        } catch (e) {
            status = 'error';
        }

        //The user has left the page in the meantime
        if (this._disconnected) {
            return;
        }

        switch (status) {
            case 'updated':
                this._finish();
                break;
            case 'unchanged':
                //If we had to wait for another request, that one has fetched the data, otherwise there was nothing to do
                if (this._waited) {
                    this._finish();
                } else {
                    this.element.remove();
                }
                break;
            case 'busy':
                if (this._attempts < this.constructor.MAX_ATTEMPTS) {
                    this._waited = true;
                    this._timeout = setTimeout(() => this._fetch(), this.constructor.RETRY_DELAY);
                } else {
                    this._fail('error');
                }
                break;
            default:
                this._fail(status === 'limit' ? 'limit' : 'error');
        }
    }

    /** The data is there: say so, and show the page with the new data */
    _finish() {
        this.spinnerTarget.classList.add('d-none');
        this.doneTarget.classList.remove('d-none');
        this.element.classList.replace('alert-info', 'alert-success');
        this.messageTarget.textContent = this.messagesValue.updated;

        window.Turbo.visit(window.location.href, {action: 'replace'});
    }

    _fail(reason) {
        this.spinnerTarget.classList.add('d-none');
        this.failedTarget.classList.remove('d-none');
        this.element.classList.replace('alert-info', 'alert-warning');
        this.messageTarget.textContent = this.messagesValue[reason];
    }
}
