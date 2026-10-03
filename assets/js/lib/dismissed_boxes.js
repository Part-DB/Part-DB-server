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

/**
 * Keeps track of the boxes (like the ones on the homepage), which the user has closed.
 *
 * The IDs of the closed boxes are stored in a cookie instead of the local storage, so that the server can render
 * these boxes already hidden (see templates/components/dismissible.macro.html.twig) and they do not flash up
 * for a moment on page load.
 */

const COOKIE_NAME = 'dismissed_boxes';
const COOKIE_MAX_AGE = 365 * 24 * 60 * 60;

/**
 * The name of the event, which is dispatched on the window, when the list of closed boxes has changed.
 * @type {string}
 */
export const DISMISSED_BOXES_CHANGED_EVENT = 'dismissed-boxes:changed';

/**
 * Returns the IDs of all boxes, which were closed by the user.
 * @return {string[]}
 */
export function getDismissedBoxes() {
    const match = document.cookie.match(new RegExp('(?:^|;\\s*)' + COOKIE_NAME + '=([^;]*)'));
    if (!match) {
        return [];
    }

    try {
        return decodeURIComponent(match[1]).split(',').filter(id => id !== '');
    } catch (e) {
        return [];
    }
}

/**
 * Stores the IDs of the boxes, which were closed by the user, and notifies all listeners about the change.
 * @param {string[]} ids
 */
export function setDismissedBoxes(ids) {
    const max_age = ids.length > 0 ? COOKIE_MAX_AGE : 0;
    document.cookie = COOKIE_NAME + '=' + encodeURIComponent(ids.join(','))
        + '; path=/; max-age=' + max_age + '; SameSite=Lax';

    window.dispatchEvent(new CustomEvent(DISMISSED_BOXES_CHANGED_EVENT));
}
