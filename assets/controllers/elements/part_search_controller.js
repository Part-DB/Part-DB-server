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

import {
    trans,
} from '../../translator';


//The key under which the chosen ordering of the results is remembered in the local storage
const SORT_STORAGE_KEY = 'part_search_sort';

//The orderings which can be chosen in the results dropdown (key as understood by the server and the icon of the toggle)
const SORT_OPTIONS = [
    {key: 'name', icon: 'fa-font'},
    {key: 'manufacturer', icon: 'fa-industry'},
    {key: 'supplier', icon: 'fa-truck'},
    {key: 'added', icon: 'fa-calendar-plus'},
    {key: 'modified', icon: 'fa-pen'},
    {key: 'top_category', icon: 'fa-sitemap'},
    {key: 'category', icon: 'fa-tags'},
];

/**
 * This controller is responsible for the search fields in the navbar and the homepage.
 * It uses the Algolia Autocomplete library to provide a fast and responsive search.
 */
export default class extends Controller {

    static targets = ["input", "sort", "sortDir"];

    _autocomplete;

    // Highlight the search query in the results
    _highlight = (text, query, options = null) => {
        if (!text) return text;
        if (!query) return text;

        const HIGHLIGHT_PRE_TAG = '__aa-highlight__'
        const HIGHLIGHT_POST_TAG = '__/aa-highlight__'

        const escape = (str) => str.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&');
        let regex;
        try {
            if (options && options.has('regex')) {
                regex = new RegExp(query, 'gi');
            } else if (options && options.has('extensive')) {
                // Extensive search matches each word on its own
                const tokens = query.split(/[\s+]+/).filter((token) => token !== '');
                regex = new RegExp(tokens.map(escape).join('|'), 'gi');
            } else {
                regex = new RegExp(escape(query), 'gi');
            }
        } catch (e) {
            // The server-side regex dialect can differ from the JS one, just don't highlight then
            return text;
        }

        return text.replace(regex, (match) => match === '' ? match : `${HIGHLIGHT_PRE_TAG}${match}${HIGHLIGHT_POST_TAG}`);
    }

    /**
     * Returns the checked search options (fields, regex, extensive, wildcard) of the search form, if regex or
     * extensive matching is enabled. Otherwise null is returned and the default autocomplete search is used.
     * @returns {URLSearchParams|null}
     */
    _getSearchOptions() {
        const form = this.element.closest('form');
        if (!form) {
            return null;
        }

        const options = new URLSearchParams();
        // Disabled checkboxes (e.g. extensive while regex is active) would not be submitted by the form either
        form.querySelectorAll('input[type="checkbox"]:checked:not(:disabled)').forEach((checkbox) => {
            options.set(checkbox.name, checkbox.value || '1');
        });

        if (!options.has('regex') && !options.has('extensive')) {
            return null;
        }

        return options;
    }

    /**
     * Returns the ordering chosen for the results, or null if the default ordering is used.
     * It is read from the local storage every time, so all search fields on the page share it.
     * @returns {{key: string, desc: boolean}|null}
     */
    _getSort() {
        let value = null;
        try {
            value = localStorage.getItem(SORT_STORAGE_KEY);
        } catch (e) {
            // Without local storage the default ordering is used
        }
        if (!value) {
            return null;
        }

        const [key, direction] = value.split(':');
        if (!SORT_OPTIONS.some((option) => option.key === key)) {
            return null;
        }

        return {key: key, desc: direction === 'desc'};
    }

    /**
     * Cycles the given ordering: ascending on the first click, descending on the second and back to the default
     * ordering on the third one. Only one ordering can be active at a time.
     * @param {string} key
     */
    _toggleSort(key) {
        const current = this._getSort();

        try {
            if (!current || current.key !== key) {
                localStorage.setItem(SORT_STORAGE_KEY, key + ':asc');
            } else if (!current.desc) {
                localStorage.setItem(SORT_STORAGE_KEY, key + ':desc');
            } else {
                localStorage.removeItem(SORT_STORAGE_KEY);
            }
        } catch (e) {
            return;
        }

        this._autocomplete.refresh();
    }

    /**
     * Put the chosen ordering into the form, so the search page is sorted the same way as the dropdown.
     */
    _updateSortInputs() {
        if (!this.hasSortTarget || !this.hasSortDirTarget) {
            return;
        }

        const sort = this._getSort();
        this.sortTarget.disabled = this.sortDirTarget.disabled = !sort;
        this.sortTarget.value = sort ? sort.key : '';
        this.sortDirTarget.value = sort && sort.desc ? 'desc' : 'asc';
    }

    /**
     * Renders the small toggles used to choose the ordering of the results.
     */
    _renderSortToggles(html) {
        const sort = this._getSort();

        return html`<span class="aa-SourceHeaderSort" role="group" aria-label="${trans("search.sort.label")}">
            ${SORT_OPTIONS.map((option) => {
                const active = sort && sort.key === option.key;
                const title = trans("search.sort.label") + ': ' + trans("search.sort." + option.key)
                    + (active ? ' (' + trans(sort.desc ? "search.sort.descending" : "search.sort.ascending") + ')' : '');

                //The mousedown default is prevented, so the search input keeps the focus and the dropdown stays open
                return html`<button type="button" tabindex="-1" key="${option.key}"
                        class="aa-SourceHeaderSortToggle ${active ? 'aa-SourceHeaderSortToggle--active' : ''}"
                        title="${title}" aria-label="${title}" aria-pressed="${active ? 'true' : 'false'}"
                        onMouseDown="${(event) => event.preventDefault()}"
                        onClick="${(event) => { event.preventDefault(); this._toggleSort(option.key); }}">
                    <i class="fa-solid fa-fw ${option.icon}"></i>
                    ${active ? html`<i class="fa-solid ${sort.desc ? 'fa-arrow-down' : 'fa-arrow-up'}"></i>` : ''}
                </button>`;
            })}
        </span>`;
    }

    initialize() {
        // The endpoint for searching parts
        const base_url = this.element.dataset.autocomplete;
        // The URL template for the part detail pages
        const part_detail_uri_template = this.element.dataset.detailUrl;

        //The URL of the placeholder picture
        const placeholder_image = this.element.dataset.placeholderImage;

        //If the element is in navbar mode, or not
        const navbar_mode = this.element.dataset.navbarMode === "true";

        const that = this;

        const recentSearchesPlugin = createLocalStorageRecentSearchesPlugin({
            key: 'RECENT_SEARCH',
            limit: 5,
        });

        this._autocomplete = autocomplete({
            container: this.element,
            //Place the panel in the navbar, if the element is in navbar mode
            panelContainer: navbar_mode ? document.getElementById("navbar-search-form") : document.body,
            panelPlacement: this.element.dataset.panelPlacement,
            plugins: [recentSearchesPlugin],
            openOnFocus: true,
            placeholder: trans("search.placeholder"),
            translations: {
                submitButtonTitle: trans("search.submit")
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

                //Do not submit the form, if the input is empty
                if (state.query === "") {
                    return;
                }

                input.value = state.query;
                that._updateSortInputs();
                input.form.requestSubmit();
            },


            getSources({ query }) {
                return [
                    // The parts source
                    {
                        sourceId: 'parts',
                        getItems() {
                            let url = base_url.replace('__QUERY__', encodeURIComponent(query));

                            // Pass the search options, so regex and extensive matching also work in the dropdown
                            const options = that._getSearchOptions();
                            const params = new URLSearchParams(options ?? undefined);

                            // And the chosen ordering of the results
                            const sort = that._getSort();
                            if (sort) {
                                params.set('sort', sort.key);
                                params.set('sort_dir', sort.desc ? 'desc' : 'asc');
                            }

                            if (params.toString() !== '') {
                                url += (url.includes('?') ? '&' : '?') + params.toString();
                            }

                            const data = fetch(url)
                                .then((response) => response.json())
                                ;

                            //Iterate over all fields besides the id and highlight them
                            const fields = ["name", "description", "category", "footprint"];

                            data.then((items) => {
                                items.forEach((item) => {
                                    for (const field of fields) {
                                        item[field] = that._highlight(item[field], query, options);
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
                                return html`<span class="aa-SourceHeaderTitle">${trans("part.labelp")}</span>
                                    ${that._renderSortToggles(html)}
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

        //Try to find the input field and register a defocus handler. This is necessarry, as by default the autocomplete
        //lib has problems when multiple inputs are present on the page. (see https://github.com/algolia/autocomplete/issues/1216)
        const inputs = this.element.getElementsByClassName('aa-Input');
        for (const input of inputs) {
            input.addEventListener('blur', () => {
                this._autocomplete.setIsOpen(false);
            });
        }

        // Changing a search option should update the results for the already typed query
        const form = this.element.closest('form');
        if (form) {
            form.addEventListener('change', (event) => {
                if (event.target.matches('input[type="checkbox"]') && inputs.length > 0 && inputs[0].value !== '') {
                    this._autocomplete.refresh();
                }
            });
        }

    }
}
