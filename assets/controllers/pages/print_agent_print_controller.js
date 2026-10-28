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
import {getAgent, handoffUrl} from "../../helpers/print_agent";

/**
 * Print button for the print agent connected on /label/agent. Hidden while this browser has no agent.
 */
export default class extends Controller
{
    static values = {
        type: String,
        ids: String,
        profile: Number,
    };

    connect() {
        this.element.classList.toggle('d-none', getAgent() === null);
    }

    async print(event) {
        event.preventDefault();
        const agent = getAgent();
        if (!agent) {
            return;
        }
        // Open the window now, while the click still counts as a user action: after the
        // async signing below, popup blockers would stop it.
        const win = window.open('', 'pdb-print', 'width=420,height=320');
        const url = await handoffUrl(agent, {type: this.typeValue, ids: this.idsValue, profile: this.profileValue});
        if (win) {
            win.location.href = url;
        } else {
            location.href = url;
        }
    }
}
