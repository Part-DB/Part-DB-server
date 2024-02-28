/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

import { Controller } from "@hotwired/stimulus";
import { autocomplete } from '@algolia/autocomplete-js';
//import "@algolia/autocomplete-theme-classic/dist/theme.css";
import "../../css/components/autocomplete_bootstrap_theme.css";
import { createLocalStorageRecentSearchesPlugin } from '@algolia/autocomplete-plugin-recent-searches';
import {marked} from "marked";

export default class extends Controller {

    static targets = ["input"];

    _autocomplete;

    connect() {
        // The endpoint for searching parts
        const base_url = this.element.dataset.autocomplete;
        // The URL template for the part detail pages
        const part_detail_uri_template = this.element.dataset.detailUrl;

        const that = this;

        const recentSearchesPlugin = createLocalStorageRecentSearchesPlugin({
            key: 'RECENT_SEARCH',
            limit: 5,
        });

        this._autocomplete = autocomplete({
            container: this.element,
            panelContainer: document.body,
            panelPlacement: 'end',
            plugins: [recentSearchesPlugin],
            openOnFocus: true,
            placeholder: "Search for parts",
            onSubmit({state, event, ...setters}) {
                //Put the current text into each target input field
                const input = that.inputTarget;

                if (!input) {
                    return;
                }

                input.value = state.query;
                input.form.requestSubmit();
            },
            getSources({ query }) {
                return [
                    {
                        sourceId: 'parts',
                        getItems() {
                            const url = base_url.replace('__QUERY__', encodeURIComponent(query));

                            return fetch(url)
                                .then((response) => response.json());
                        },
                        templates: {
                            header({ html }) {
                                return html`<span class="aa-SourceHeaderTitle">Parts</span>
                                    <div class="aa-SourceHeaderLine" />`;
                            },
                            item({item, components, html}) {
                                const details_url = part_detail_uri_template.replace('__ID__', item.id);


                                return html`
                                    <a class="aa-ItemLink" href="${details_url}">
                                        <div class="aa-ItemContent">
                                            <div class="aa-ItemIcon aa-ItemIcon--picture aa-ItemIcon--alignTop">
                                                <img src="${item.image}" alt="${item.name}" width="40" height="40"/>
                                            </div>
                                            <div class="aa-ItemContentBody">
                                                <div class="aa-ItemContentTitle">
                                                    ${components.Highlight({hit: item, attribute: 'name'})}
                                                </div>
                                                <div class="aa-ItemContentDescription">
                                                    ${components.Snippet({hit: item, attribute: 'description'})}
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                `;
                            },
                        },
                    },
                ];
            },
        });
    }
}