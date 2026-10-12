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
 * Hover prefetching ("InstantClick") for navigation targets which are not real <a href> elements (e.g. treeview nodes),
 * and therefore are not picked up by Turbo's built-in link prefetching.
 *
 * It mimics Turbo's behavior: after hovering for a short delay, the page is fetched in the background. When Turbo later
 * performs a request to the same URL, the prefetched response is handed to Turbo via the `fetchRequest` property of the
 * turbo:before-fetch-request event (the same mechanism Turbo uses internally for its own prefetch cache).
 * It respects the <meta name="turbo-prefetch"> and <meta name="turbo-prefetch-cache-time"> tags.
 */

import {fetch as turboFetch} from "@hotwired/turbo";

//Same values as Turbo uses for its link prefetching
const PREFETCH_DELAY = 100;
const DEFAULT_CACHE_TTL = 10 * 1000;

/** @type {{key: string, response: Promise<Response>, expires: number}|null} */
let cached = null;
let pendingTimeout = null;

function getMetaContent(name) {
    return document.head.querySelector(`meta[name="${name}"]`)?.content;
}

/**
 * Normalize the URL the same way Turbo's FetchRequest does, so that the keys match
 * @param {string|URL} url
 * @returns {string}
 */
function toCacheKey(url) {
    const normalized = new URL(url, document.baseURI);
    normalized.search = new URLSearchParams(normalized.search).toString();
    normalized.hash = "";
    return normalized.href;
}

/**
 * Schedule a prefetch of the given URL. The request is only performed, if prefetchCancel() is not called within the
 * prefetch delay (e.g. because the mouse left the element again).
 * @param {string} url The URL to prefetch
 * @param {string|null} frame The id of the turbo frame the URL will be loaded into, or null for a full page visit
 */
export function prefetchLater(url, frame = null) {
    cancelPrefetch();

    if (getMetaContent("turbo-prefetch") === "false") {
        return;
    }

    const key = toCacheKey(url);
    //Only same-origin requests can be handled by Turbo
    if (new URL(key).origin !== window.location.origin) {
        return;
    }

    //Already prefetched and still fresh
    if (cached && cached.key === key && cached.expires > Date.now()) {
        return;
    }

    pendingTimeout = setTimeout(() => {
        pendingTimeout = null;

        const headers = {
            "Accept": "text/html, application/xhtml+xml",
            "X-Sec-Purpose": "prefetch",
        };
        if (frame && frame !== "_top") {
            headers["Turbo-Frame"] = frame;
        }

        const response = turboFetch(key, {
            method: "GET",
            credentials: "same-origin",
            redirect: "follow",
            headers: headers,
            priority: "low",
        });
        //Avoid unhandled rejection warnings, if the prefetched response is never used. Turbo handles errors of used responses.
        response.catch(() => {});

        cached = {
            key: key,
            response: response,
            expires: Date.now() + (Number(getMetaContent("turbo-prefetch-cache-time")) || DEFAULT_CACHE_TTL),
        };
    }, PREFETCH_DELAY);
}

/**
 * Cancel a scheduled (not yet started) prefetch request.
 */
export function cancelPrefetch() {
    if (pendingTimeout) {
        clearTimeout(pendingTimeout);
        pendingTimeout = null;
    }
}

document.addEventListener("turbo:before-fetch-request", (event) => {
    if (!cached || event.target.tagName === "FORM" || event.detail.fetchOptions.method !== "GET" || event.detail.fetchRequest) {
        return;
    }

    if (cached.key !== toCacheKey(event.detail.url)) {
        return;
    }

    const entry = cached;
    //A response body can only be consumed once, so the entry is discarded in any case
    cached = null;

    if (entry.expires > Date.now()) {
        event.detail.fetchRequest = {response: entry.response};
    }
}, true);
