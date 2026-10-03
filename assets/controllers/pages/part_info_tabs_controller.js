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
import {Tab} from "bootstrap";

/**
 * This controller allows to show several tab panes of the part info page at once.
 *
 * A tab link can stand for several panes, by listing their IDs comma separated in its href (like "#part_lots,comment").
 * Bootstrap only toggles the first of them (given as data-bs-target), this controller takes care of the others.
 * The same notation can be used in the URL fragment, to show any combination of panes together, in the given order.
 */
export default class extends Controller {
    static targets = ["content", "heading"];

    connect() {
        //Whether the shown panes were chosen by the URL fragment, and do not belong to a single tab link
        this._adhoc = false;

        this._onShow = (event) => {
            //Ignore tabs nested inside of the panes
            if (this._links().includes(event.target)) {
                this._showPanes(this._paneIDs(event.target), [event.target]);
            }
        };
        this._onClick = this._handleClick.bind(this);
        this._onHashChange = () => this._showFromHash();

        this.element.addEventListener('show.bs.tab', this._onShow);
        this.element.addEventListener('click', this._onClick);
        window.addEventListener('hashchange', this._onHashChange);

        this._showFromHash();
    }

    disconnect() {
        this.element.removeEventListener('show.bs.tab', this._onShow);
        this.element.removeEventListener('click', this._onClick);
        window.removeEventListener('hashchange', this._onHashChange);
    }

    /**
     * Returns all tab links, which can be selected by the user.
     * @returns {HTMLElement[]}
     */
    _links() {
        return Array.from(this.element.querySelectorAll('.nav-tabs .nav-link[href^="#"]:not(.disabled)'));
    }

    /**
     * Returns the IDs of the panes, which are shown by the given tab link.
     * @param {HTMLElement} link
     * @returns {string[]}
     */
    _paneIDs(link) {
        return link.getAttribute('href').substring(1).split(',');
    }

    /**
     * Shows the panes given in the URL fragment.
     */
    _showFromHash() {
        const links = this._links();
        const available = links.flatMap(link => this._paneIDs(link));

        let requested = [];
        try {
            requested = decodeURIComponent(window.location.hash.substring(1)).split(',');
        } catch (e) {
            return;
        }
        //Ignore unknown and duplicate panes
        const ids = requested.filter((id, index) => available.includes(id) && requested.indexOf(id) === index);

        if (ids.length === 0) {
            return;
        }

        //If a tab link stands for exactly these panes, use it
        let link = links.find(link => this._paneIDs(link).join(',') === ids.join(','));
        let scrollTarget = null;

        //A single pane, which is part of a combined tab, is shown in its tab
        if (!link && ids.length === 1) {
            link = links.find(link => this._paneIDs(link).includes(ids[0]));
            scrollTarget = document.getElementById(ids[0]);
        }

        if (!link) {
            //Otherwise show the wished panes together, and mark the tabs which are shown completely
            this._showPanes(ids, links.filter(link => this._paneIDs(link).every(id => ids.includes(id))));
            this._adhoc = true;
            return;
        }

        if (this._adhoc || link.classList.contains('active')) {
            this._showPanes(this._paneIDs(link), [link]);
        } else {
            Tab.getOrCreateInstance(link).show();
        }

        if (scrollTarget) {
            scrollTarget.scrollIntoView();
        }
    }

    /**
     * Bootstrap ignores clicks on an already active tab. But if panes were combined by the URL fragment, multiple tabs
     * can be active, and the user must be able to select one of them.
     * @param {Event} event
     */
    _handleClick(event) {
        const link = event.target.closest('.nav-link');

        if (!this._adhoc || !link || !link.classList.contains('active') || !this._links().includes(link)) {
            return;
        }

        this._showPanes(this._paneIDs(link), [link]);
        //Bootstrap fires no event here, so we have to update the URL by ourselves
        history.replaceState(null, null, link.getAttribute('href'));
    }

    /**
     * Shows exactly the panes with the given IDs (in that order) and marks the given tab links as active.
     * @param {string[]} ids
     * @param {HTMLElement[]} activeLinks
     */
    _showPanes(ids, activeLinks) {
        const combined = ids.length > 1;

        for (const pane of this.contentTarget.querySelectorAll(':scope > .tab-pane')) {
            const index = ids.indexOf(pane.id);
            pane.classList.toggle('active', index >= 0);
            pane.classList.toggle('show', index >= 0);
            pane.style.order = (combined && index >= 0) ? index : '';
        }

        //The order of the panes is realized via flexbox, each pane gets a heading to tell them apart
        this.contentTarget.classList.toggle('d-flex', combined);
        this.contentTarget.classList.toggle('flex-column', combined);
        for (const heading of this.headingTargets) {
            heading.classList.toggle('d-none', !combined);
        }

        for (const link of this._links()) {
            const active = activeLinks.includes(link);
            link.classList.toggle('active', active);
            link.setAttribute('aria-selected', active ? 'true' : 'false');
        }

        this._adhoc = false;
    }
}
