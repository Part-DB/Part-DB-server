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

const STORAGE_KEY = 'hide_sidebar';

/**
 * The class which is set on the root (html) element, when the sidebar is hidden. The actual sliding is done in CSS
 * (see layout.css). The class is put on the root element, so that it can be applied by an inline script in the
 * base template before the page is rendered, and is not lost on Turbo navigations.
 */
const HIDDEN_CLASS = 'sidebar-hidden';

export default class extends Controller {
    /**
     * The element of the container which is expanded to the full width.
     * @type {HTMLElement}
     * @private
     */
    _container;

    connect() {
        this._container = document.getElementById('main');

        this._onTransitionEnd = this._onTransitionEnd.bind(this);
        this._container?.addEventListener('transitionend', this._onTransitionEnd);

        this._onKeydown = this._onKeydown.bind(this);
        document.addEventListener('keydown', this._onKeydown);

        //Make the state persistent over reloads
        this._apply(this._readState());
    }

    disconnect() {
        this._container?.removeEventListener('transitionend', this._onTransitionEnd);
        document.removeEventListener('keydown', this._onKeydown);
    }

    /**
     * Pressing "[" anywhere on the page (outside of text fields) toggles the sidebar, like the button does.
     */
    _onKeydown(event) {
        if (event.key !== '[' || event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented) {
            return;
        }

        const active = document.activeElement;
        if (active && (active.isContentEditable || active.closest('input, textarea, select, [contenteditable]'))) {
            return;
        }

        //The button is not shown on small screens, where the sidebar is collapsed into the navbar instead
        //(offsetParent can not be used for this check: it is always null for the fixed positioned button)
        if (this.element.getClientRects().length === 0) {
            return;
        }

        event.preventDefault();
        this.toggleSidebar();
    }

    get hidden() {
        return document.documentElement.classList.contains(HIDDEN_CLASS);
    }

    hideSidebar() {
        this._apply(true);
        this._saveState(true);
    }

    showSidebar() {
        this._apply(false);
        this._saveState(false);
    }

    toggleSidebar() {
        if (this.hidden) {
            this.showSidebar();
        } else {
            this.hideSidebar();
        }

        //Hide the tootip on the button
        this.element.blur();

        //If nothing is animated (e.g. reduced motion), no transitionend event is fired, so notify directly
        if (!this._container || parseFloat(getComputedStyle(this._container).transitionDuration) === 0) {
            this._notifyResize();
        }
    }

    _apply(hidden) {
        document.documentElement.classList.toggle(HIDDEN_CLASS, hidden);
        this.element.setAttribute('aria-expanded', hidden ? 'false' : 'true');
    }

    _readState() {
        try {
            return localStorage.getItem(STORAGE_KEY) === 'true';
        } catch (e) {
            return this.hidden;
        }
    }

    _saveState(hidden) {
        try {
            localStorage.setItem(STORAGE_KEY, hidden ? 'true' : 'false');
        } catch (e) {
            //Storage is not available, the state is just not persisted then
        }
    }

    _onTransitionEnd(event) {
        if (event.target === this._container && event.propertyName === 'width') {
            this._notifyResize();
        }
    }

    /**
     * The width of the content area has changed, without the window being resized. Tell everybody who sizes itself
     * according to the available width (like datatables with its columns and fixed header) to recalculate.
     * @private
     */
    _notifyResize() {
        window.dispatchEvent(new Event('resize'));
    }
}
