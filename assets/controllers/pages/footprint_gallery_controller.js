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
import "../../css/components/footprint_gallery.css";

/**
 * Filters the builtin footprints gallery by filename or folder path, and handles the folder tree navigation
 * (jumping to a folder and highlighting the folder currently visible).
 */
export default class extends Controller {
    static targets = ["input", "group", "item", "noResults", "treeNode", "treeToggle", "treeContainer"];

    connect() {
        //Highlight the folder, whose heading is currently at the top of the viewport
        this._observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    this.setActiveFolder(entry.target.dataset.folder);
                }
            }
        }, {rootMargin: '-80px 0px -70% 0px'});

        this.groupTargets.forEach(group => this._observer.observe(group));
    }

    disconnect() {
        this._observer?.disconnect();
    }

    filter() {
        //Split the query into words, all of them must match (in any order)
        const words = this.inputTarget.value.toLowerCase().split(/\s+/).filter(w => w !== '');
        const visibleFolders = [];

        for (const group of this.groupTargets) {
            let groupVisible = false;

            for (const item of group.querySelectorAll('[data-pages--footprint-gallery-target="item"]')) {
                const haystack = item.dataset.search;
                const visible = words.every(w => haystack.includes(w));
                item.classList.toggle('d-none', !visible);
                groupVisible ||= visible;
            }

            group.classList.toggle('d-none', !groupVisible);
            if (groupVisible) {
                visibleFolders.push(group.dataset.folder);
            }
        }

        //Only show tree nodes, which still contain matching images
        for (const node of this.treeNodeTargets) {
            node.classList.toggle('d-none', !visibleFolders.some(f => this._isInFolder(f, node.dataset.folder)));
        }

        this.noResultsTarget.classList.toggle('d-none', visibleFolders.length > 0);
    }

    jump(event) {
        event.preventDefault();
        const folder = event.currentTarget.dataset.folder;

        //Folders without own images (or whose images are filtered out) jump to their first visible subfolder
        const group = this.groupTargets.find(g => !g.classList.contains('d-none') && this._isInFolder(g.dataset.folder, folder));
        if (!group) {
            return;
        }

        group.scrollIntoView({behavior: 'smooth', block: 'start'});
        history.replaceState(history.state, '', '#' + group.firstElementChild.id);

        //On small screens the tree overlays the gallery, so close it after jumping
        if (this.hasTreeToggleTarget && this.treeToggleTarget.offsetParent !== null) {
            Collapse.getOrCreateInstance(this.treeContainerTarget, {toggle: false}).hide();
        }
    }

    setActiveFolder(folder) {
        for (const link of this.treeContainerTarget.querySelectorAll('.footprint-gallery-tree-link.active')) {
            link.classList.remove('active');
        }

        const link = this.treeContainerTarget.querySelector(`.footprint-gallery-tree-link[data-folder="${CSS.escape(folder)}"]`);
        if (!link) {
            return;
        }
        link.classList.add('active');

        //Expand the parent folders, so the active entry is visible
        for (let details = link.closest('details'); details; details = details.parentElement.closest('details')) {
            //The link of a folder with subfolders lives in the summary, keep that one as it is
            if (!details.firstElementChild.contains(link)) {
                details.open = true;
            }
        }

        //Scroll the tree (not the page) so the active entry is visible
        const container = this.treeContainerTarget;
        if (container.offsetParent !== null && container.scrollHeight > container.clientHeight) {
            const linkRect = link.getBoundingClientRect();
            const containerRect = container.getBoundingClientRect();
            if (linkRect.top < containerRect.top || linkRect.bottom > containerRect.bottom) {
                container.scrollTop += linkRect.top - containerRect.top - container.clientHeight / 2;
            }
        }
    }

    _isInFolder(folder, parent) {
        return folder === parent || folder.startsWith(parent + '/');
    }
}
