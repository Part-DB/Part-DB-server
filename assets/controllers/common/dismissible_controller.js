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
 * A box, which can be closed by the user and stays hidden, until the hidden boxes are restored
 * (see dismissible_restore_controller.js).
 * The element must have an (unique) ID value and the close button must trigger the dismiss action.
 */
export default class extends Controller {
    static values = {
        id: String,
    };

    connect() {
        this._update = this.update.bind(this);
        window.addEventListener(DISMISSED_BOXES_CHANGED_EVENT, this._update);
        this.update();
    }

    disconnect() {
        window.removeEventListener(DISMISSED_BOXES_CHANGED_EVENT, this._update);
    }

    /**
     * Shows or hides the box, depending on whether it was closed by the user.
     */
    update() {
        this.element.hidden = getDismissedBoxes().includes(this.idValue);
    }

    /**
     * Hides the box permanently.
     */
    dismiss() {
        const ids = getDismissedBoxes();
        if (!ids.includes(this.idValue)) {
            ids.push(this.idValue);
        }
        setDismissedBoxes(ids);
    }
}
