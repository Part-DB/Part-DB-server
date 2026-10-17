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
import {DISMISSED_BOXES_CHANGED_EVENT, getDismissedBoxes, setDismissedBoxes} from "../../js/lib/dismissed_boxes";

/**
 * A button, which brings back all boxes, that were closed by the user (see dismissible_controller.js).
 * It is only visible, if there is something to restore.
 */
export default class extends Controller {

    connect() {
        this._update = this.update.bind(this);
        window.addEventListener(DISMISSED_BOXES_CHANGED_EVENT, this._update);

        //Store the list again, so that it does not expire, as long as the user keeps visiting
        const ids = getDismissedBoxes();
        if (ids.length > 0) {
            setDismissedBoxes(ids);
        }

        this.update();
    }

    disconnect() {
        window.removeEventListener(DISMISSED_BOXES_CHANGED_EVENT, this._update);
    }

    /**
     * Shows the button only, if there are closed boxes.
     */
    update() {
        this.element.hidden = getDismissedBoxes().length === 0;
    }

    /**
     * Shows all closed boxes again.
     */
    restore() {
        setDismissedBoxes([]);
    }
}
