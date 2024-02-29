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

import {
    trans,
    SEARCH_PLACEHOLDER,
    SEARCH_SUBMIT,
    STATISTICS_PARTS
} from '../../translator';

export default class extends Controller {

    static targets = ["input"];

    _autocomplete;

    _highlight = (text, query) => {
        if (!text) return text;
        if (!query) return text;

        const HIGHLIGHT_PRE_TAG = '__aa-highlight__'
        const HIGHLIGHT_POST_TAG = '__/aa-highlight__'

        const escaped = query.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&');
        const regex = new RegExp(escaped, 'gi');

        return text.replace(regex, (match) => `${HIGHLIGHT_PRE_TAG}${match}${HIGHLIGHT_POST_TAG}`);
    }

    initialize() {
        // The endpoint for searching parts
        const base_url = this.element.dataset.autocomplete;
        // The URL template for the part detail pages
        const part_detail_uri_template = this.element.dataset.detailUrl;

        //The URL of the placeholder picture
        const placeholder_image = this.element.dataset.placeholderImage;

        const that = this;

        const recentSearchesPlugin = createLocalStorageRecentSearchesPlugin({
            key: 'RECENT_SEARCH',
            limit: 5,
        });

        this._autocomplete = autocomplete({
            container: this.element,
            panelContainer: document.getElementById("navbar-frame"),
            panelPlacement: 'end',
            plugins: [recentSearchesPlugin],
            openOnFocus: true,
            placeholder: trans(SEARCH_PLACEHOLDER),
            translations: {
                submitButtonTitle: trans(SEARCH_SUBMIT)
            },

            // Use a navigator compatible with turbo:
            navigator: {
                navigate({ itemUrl }) {
                    window.Turbo.visit(itemUrl, { action: "advance" });
                },
                navigateNewTab({ itemUrl }) {
                    const windowReference = window.open(itemUrl, '_blank', 'noopener');

                    if (windowReference) {
                        windowReference.focus();
                    }
                },
                navigateNewWindow({ itemUrl }) {
                    window.open(itemUrl, '_blank', 'noopener');
                },
            },

            // If the form is submitted, forward the term to the form
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
                    // The parts source
                    {
                        sourceId: 'parts',
                        getItems() {
                            const url = base_url.replace('__QUERY__', encodeURIComponent(query));

                            const data = fetch(url)
                                .then((response) => response.json())
                                ;

                            //Iterate over all fields besides the id and highlight them
                            const fields = ["name", "description", "category", "footprint"];

                            data.then((items) => {
                                items.forEach((item) => {
                                    for (const field of fields) {
                                        item[field] = that._highlight(item[field], query);
                                    }
                                });
                            });

                            return data;
                        },
                        getItemUrl({ item }) {
                            return part_detail_uri_template.replace('__ID__', item.id);
                        },
                        templates: {
                            header({ html }) {
                                return html`<span class="aa-SourceHeaderTitle">${trans(STATISTICS_PARTS)}</span>
                                    <div class="aa-SourceHeaderLine" />`;
                            },
                            item({item, components, html}) {
                                const details_url = part_detail_uri_template.replace('__ID__', item.id);

                                return html`
                                    <a class="aa-ItemLink" href="${details_url}">
                                        <div class="aa-ItemContent">
                                            <div class="aa-ItemIcon aa-ItemIcon--picture aa-ItemIcon--alignTop">
                                                <img src="${item.image !== "" ? item.image : placeholder_image}" alt="${item.name}" width="30" height="30"/>
                                            </div>
                                            <div class="aa-ItemContentBody">
                                                <div class="aa-ItemContentTitle">
                                                    <b>
                                                        ${components.Highlight({hit: item, attribute: 'name'})}
                                                    </b>
                                                </div>
                                                <div class="aa-ItemContentDescription">
                                                    ${components.Highlight({hit: item, attribute: 'description'})}
                                                    ${item.category ? html`<p class="m-0"><span class="fa-solid fa-tags fa-fw"></span>${components.Highlight({hit: item, attribute: 'category'})}</p>` : ""}
                                                    ${item.footprint ? html`<p class="m-0"><span class="fa-solid fa-microchip fa-fw"></span>${components.Highlight({hit: item, attribute: 'footprint'})}</p>` : ""}
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