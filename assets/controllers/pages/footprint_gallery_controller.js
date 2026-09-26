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
 * Filters the builtin footprints gallery by filename or folder path.
 */
export default class extends Controller {
    static targets = ["input", "group", "item", "noResults"];

    filter() {
        //Split the query into words, all of them must match (in any order)
        const words = this.inputTarget.value.toLowerCase().split(/\s+/).filter(w => w !== '');
        let anyVisible = false;

        for (const group of this.groupTargets) {
            let groupVisible = false;

            for (const item of group.querySelectorAll('[data-pages--footprint-gallery-target="item"]')) {
                const haystack = item.dataset.search;
                const visible = words.every(w => haystack.includes(w));
                item.classList.toggle('d-none', !visible);
                groupVisible ||= visible;
            }

            group.classList.toggle('d-none', !groupVisible);
            anyVisible ||= groupVisible;
        }

        this.noResultsTarget.classList.toggle('d-none', anyVisible);
    }
}
