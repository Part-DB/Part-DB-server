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
import {clearAgent, getAgent, parseConnectHash, setAgent} from "../../helpers/print_agent";

/**
 * Connects this browser to a print agent from the link the agent hands out (/label/agent#url=…&key=…).
 * Saving needs a click, so a foreign link can't silently redirect labels to another agent.
 */
export default class extends Controller
{
    static targets = ['confirm', 'connected', 'invalid', 'none', 'url'];

    connect() {
        this._pending = null;
        if (location.hash.length > 1) {
            this._pending = parseConnectHash(location.hash);
            this._show(this._pending ? 'confirm' : 'invalid', this._pending?.url);
            return;
        }
        this._showCurrent();
    }

    connectAgent() {
        setAgent(this._pending.url, this._pending.key);
        this._pending = null;
        history.replaceState(null, '', location.pathname + location.search);
        this._showCurrent();
    }

    disconnectAgent() {
        clearAgent();
        this._showCurrent();
    }

    _showCurrent() {
        const agent = getAgent();
        this._show(agent ? 'connected' : 'none', agent?.url);
    }

    _show(state, url = '') {
        for (const name of ['confirm', 'connected', 'invalid', 'none']) {
            this[name + 'Target'].classList.toggle('d-none', name !== state);
        }
        this.urlTargets.forEach(el => el.textContent = url);
    }
}
