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
import {Collapse} from "bootstrap";

/**
 * The ids of the collapses, which are shown as menus on top of the page on small screens:
 * the sidebar (folder button) and the navbar menu (hamburger button).
 */
const MENU_IDS = ['sidebar-container', 'navbarContent'];

/** Must match the breakpoint, below which the sidebar is shown as an overlay (see layout.css) */
const SMALL_SCREEN_QUERY = '(max-width: 767.98px)';

/**
 * On small screens the sidebar and the navbar menu are collapses shown on top of the page. Navigation only replaces
 * the content turbo frame, so they stayed open after selecting an entry, and the user could not see that something
 * happened. Also both menus could be open at the same time and overlap each other.
 *
 * This controller closes the menus, when a navigation is started from them, and ensures that only one of them is open.
 */
export default class extends Controller {
    /**
     * The state of each menu, tracked via the bootstrap collapse events: 'showing', 'shown', 'hiding' or 'hidden'
     * @type {Object<string, string>}
     * @private
     */
    _state = {};

    /**
     * Menus, which should be closed as soon as their show transition has finished
     * @type {Set<string>}
     * @private
     */
    _closeAfterShown = new Set();

    connect() {
        this._mediaQuery = window.matchMedia(SMALL_SCREEN_QUERY);

        this._closeAll = this._closeAll.bind(this);
        this._onCollapseEvent = this._onCollapseEvent.bind(this);
        this._onClick = this._onClick.bind(this);
        this._onSubmit = this._onSubmit.bind(this);
        this._onFrameLoad = this._onFrameLoad.bind(this);

        //The treeviews in the sidebar dispatch this event, when a node is selected
        document.addEventListener('turbo:before-visit', this._closeAll);
        document.addEventListener('turbo:frame-load', this._onFrameLoad);
        document.addEventListener('click', this._onClick);
        document.addEventListener('submit', this._onSubmit);
        for (const type of ['show', 'shown', 'hide', 'hidden']) {
            document.addEventListener(type + '.bs.collapse', this._onCollapseEvent);
        }
    }

    disconnect() {
        document.removeEventListener('turbo:before-visit', this._closeAll);
        document.removeEventListener('turbo:frame-load', this._onFrameLoad);
        document.removeEventListener('click', this._onClick);
        document.removeEventListener('submit', this._onSubmit);
        for (const type of ['show', 'shown', 'hide', 'hidden']) {
            document.removeEventListener(type + '.bs.collapse', this._onCollapseEvent);
        }
    }

    _isSmallScreen() {
        return this._mediaQuery.matches;
    }

    _isMenu(element) {
        return element instanceof Element && MENU_IDS.includes(element.id);
    }

    _onCollapseEvent(event) {
        if (!this._isMenu(event.target)) {
            return;
        }
        const id = event.target.id;

        switch (event.type) {
            case 'show.bs.collapse':
                this._state[id] = 'showing';
                //Only one menu should be open at the same time
                if (this._isSmallScreen()) {
                    MENU_IDS.filter(other => other !== id).forEach(other => this._close(other));
                }
                break;
            case 'shown.bs.collapse':
                this._state[id] = 'shown';
                if (this._closeAfterShown.delete(id)) {
                    this._close(id);
                }
                break;
            case 'hide.bs.collapse':
                this._state[id] = 'hiding';
                break;
            case 'hidden.bs.collapse':
                this._state[id] = 'hidden';
                break;
        }
    }

    _onClick(event) {
        if (!this._isSmallScreen() || !(event.target instanceof Element)) {
            return;
        }

        const link = event.target.closest('a[href]');
        if (!link || !link.closest(this._menuSelector())) {
            return;
        }

        //Links which only open a dropdown or similar inside the menu should not close it
        const href = link.getAttribute('href');
        if (link.hasAttribute('data-bs-toggle') || href === '' || href === '#') {
            return;
        }

        this._closeAll();
    }

    _onSubmit(event) {
        if (this._isSmallScreen() && event.target instanceof Element && event.target.closest(this._menuSelector())) {
            this._closeAll();
        }
    }

    _onFrameLoad(event) {
        //Fallback: after new content was loaded, no menu should cover it
        if (event.target instanceof Element && event.target.id === 'content') {
            this._closeAll();
        }
    }

    _menuSelector() {
        return MENU_IDS.map(id => '#' + id).join(', ');
    }

    _closeAll() {
        if (!this._isSmallScreen()) {
            return;
        }
        MENU_IDS.forEach(id => this._close(id));
    }

    /**
     * Close the menu with the given id via the bootstrap collapse API
     * @param {string} id
     * @private
     */
    _close(id) {
        const element = document.getElementById(id);
        if (!element) {
            return;
        }

        const state = this._state[id] ?? (element.classList.contains('show') ? 'shown' : 'hidden');

        if (state === 'showing') {
            //Bootstrap ignores hide() while a transition is running, so close the menu as soon as it is fully shown
            this._closeAfterShown.add(id);
        } else if (state === 'shown') {
            Collapse.getOrCreateInstance(element, {toggle: false}).hide();
        }
    }
}
