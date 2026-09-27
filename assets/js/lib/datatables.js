/**
 * Symfony DataTables Bundle
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 *
 * CHANGED jbtronics: Ported from the original jQuery plugin ($.fn.initDataTables) to a
 * plain function, as DataTables 3 no longer requires jQuery.
 */

import DataTable from 'datatables.net';

const defaults = {
    method: 'POST',
    state: 'fragment',
    url: window.location.origin + window.location.pathname
};

function isMergeable(value) {
    return Array.isArray(value) || (value !== null && typeof value === 'object' && value.constructor === Object);
}

/**
 * Minimal replacement for jQuery's $.extend(true, ...): recursively merges plain objects and
 * arrays (by index), while every other value (including functions) is copied by reference.
 */
function deepMergeInto(target, source) {
    if (Array.isArray(source)) {
        const result = Array.isArray(target) ? target : [];
        source.forEach((value, index) => {
            result[index] = isMergeable(value) ? deepMergeInto(isMergeable(result[index]) ? result[index] : undefined, value) : value;
        });
        return result;
    }

    const result = (target !== null && typeof target === 'object' && !Array.isArray(target)) ? target : {};
    for (const key of Object.keys(source)) {
        const value = source[key];
        result[key] = isMergeable(value) ? deepMergeInto(isMergeable(result[key]) ? result[key] : undefined, value) : value;
    }
    return result;
}

function deepMerge(...sources) {
    return sources.reduce((acc, source) => (source ? deepMergeInto(acc, source) : acc), {});
}

/**
 * Minimal replacement for jQuery's $.param(): serializes a (possibly nested) object into a
 * query string using PHP-style bracket notation, which is what deparam() below expects.
 */
function param(obj) {
    const parts = [];
    const add = (key, value) => parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(value ?? ''));

    const walk = (prefix, value) => {
        // jQuery's $.ajax deep-copies the data object via $.extend(true, ...) before serializing,
        // which silently drops any key whose value is undefined. Match that here so an omitted
        // (e.g. `order: config.initial_order ?? undefined`) field isn't sent as an empty string.
        if (value === undefined) {
            return;
        }
        if (Array.isArray(value)) {
            value.forEach((v, i) => {
                if (v !== null && typeof v === 'object') {
                    walk(`${prefix}[${i}]`, v);
                } else {
                    add(`${prefix}[]`, v);
                }
            });
        } else if (value !== null && typeof value === 'object') {
            for (const key of Object.keys(value)) {
                walk(prefix ? `${prefix}[${key}]` : key, value[key]);
            }
        } else {
            add(prefix, value);
        }
    };

    for (const key of Object.keys(obj)) {
        walk(key, obj[key]);
    }

    return parts.join('&');
}

/**
 * Convert a querystring to a proper array - reverses param()
 */
function deparam(params, coerce) {
    const obj = {};
    const coerce_types = {'true': !0, 'false': !1, 'null': null};

    params.replace(/\+/g, ' ').split('&').forEach(function (v) {
        var param = v.split('='),
            key = decodeURIComponent(param[0]),
            val,
            cur = obj,
            i = 0,
            keys = key.split(']['),
            keys_last = keys.length - 1;

        if (/\[/.test(keys[0]) && /\]$/.test(keys[keys_last])) {
            keys[keys_last] = keys[keys_last].replace(/\]$/, '');
            keys = keys.shift().split('[').concat(keys);
            keys_last = keys.length - 1;
        } else {
            keys_last = 0;
        }

        if (param.length === 2) {
            val = decodeURIComponent(param[1]);

            if (coerce) {
                val = val && !isNaN(val) ? +val              // number
                    : val === 'undefined' ? undefined         // undefined
                        : coerce_types[val] !== undefined ? coerce_types[val] // true, false, null
                            : val;                                                // string
            }

            if (keys_last) {
                for (; i <= keys_last; i++) {
                    key = keys[i] === '' ? cur.length : keys[i];
                    cur = cur[key] = i < keys_last
                        ? cur[key] || (keys[i + 1] && isNaN(keys[i + 1]) ? {} : [])
                        : val;
                }

            } else {
                if (Array.isArray(obj[key])) {
                    obj[key].push(val);
                } else if (obj[key] !== undefined) {
                    obj[key] = [obj[key], val];
                } else {
                    obj[key] = val;
                }
            }

        } else if (key) {
            obj[key] = coerce
                ? undefined
                : '';
        }
    });

    return obj;
}

/**
 * Fetch helper mimicking the subset of jQuery.ajax() behaviour this module relies on: form
 * encoded request bodies for non-GET requests, query-string params for GET, and a JSON response.
 */
function ajaxRequest(url, method, data) {
    const body = param(data ?? {});
    const options = {method, credentials: 'same-origin'};
    let requestUrl = url;

    if (method && method.toUpperCase() === 'GET') {
        requestUrl += (url.includes('?') ? '&' : '?') + body;
    } else {
        options.headers = {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'};
        options.body = body;
    }

    return fetch(requestUrl, options).then(async (response) => {
        if (!response.ok) {
            const error = new Error(`Request to ${url} failed with status ${response.status}`);
            error.status = response.status;
            error.statusText = response.statusText;
            error.url = url;
            error.responseText = await response.text().catch(() => '');
            throw error;
        }

        return response.json();
    });
}

/**
 * Dispatched whenever a DataTables ajax request fails, so error_handler.js can show an alert.
 * This replaces the global jQuery `ajaxError` hook that used to cover these requests.
 */
function reportAjaxError(url, err) {
    document.dispatchEvent(new CustomEvent('dt:ajaxError', {
        detail: {
            status: err.status,
            statusText: err.statusText,
            url: err.url ?? url,
            responseText: err.responseText,
        }
    }));
}

/**
 * Initializes the datatable dynamically.
 *
 * @param {HTMLElement} root The element the datatable should be rendered into.
 * @param config
 * @param options
 */
export function initDataTables(root, config, options) {
    //Update default used url, so it reflects the current location (useful on single side apps)
    //CHANGED jbtronics: Preserve the get parameters (needed so we can pass additional params to query)
    defaults.url = window.location.origin + window.location.pathname + window.location.search;

    config = Object.assign({}, defaults, config);
    let state = '';

    // Load page state if needed
    switch (config.state) {
        case 'fragment':
            state = window.location.hash;
            break;
        case 'query':
            state = window.location.search;
            break;
    }
    state = (state.length > 1 ? deparam(state.substr(1)) : {});
    const persistOptions = config.state === 'none' ? {} : {
        stateSave: true,
        stateLoadCallback: function(s, cb) {
            // Only need stateSave to expose state() function as loading lazily is not possible otherwise
            return null;
        }
    };

    let dt;

    return new Promise((fulfill, reject) => {
        // Perform initial load
        const initialUrl = typeof config.url === 'function' ? config.url(null) : config.url;

        ajaxRequest(initialUrl, config.method, {
            _dt: config.name,
            _init: true,
            order: config.initial_order ?? undefined,
        }).then(function(data) {
            var baseState;

            // Merge all options from different sources together and add the Ajax loader
            var dtOpts = Object.assign({}, data.options, typeof config.options === 'function' ? {} : config.options, options, persistOptions, {
                ajax: function (request, drawCallback, settings) {
                    if (data) {
                        data.draw = request.draw;
                        drawCallback(data);
                        data = null;
                        if (Object.keys(state).length) {
                            var api = new DataTable.Api( settings );
                            var merged = deepMerge(api.state(), state);

                            api
                                .order(merged.order)
                                .search(merged.search.search)
                                .page.len(merged.length)
                                .page(merged.start / merged.length)
                                .draw(false);
                        }
                    } else {
                        request._dt = config.name;

                        //Try to resolve the original column index when the column was reordered (using the ColReorder plugin)
                        if (dt.colReorder && dt.colReorder.transpose) {
                            if (request.order && request.order.length) {
                                request.order.forEach(function (order) {
                                    order.column = dt.colReorder.transpose(order.column, "toOriginal");
                                });
                            }
                        }

                        const pageUrl = typeof config.url === 'function' ? config.url(dt) : config.url;
                        ajaxRequest(pageUrl, config.method, request).then(function(data) {
                            drawCallback(data);
                        }).catch(function(err) {
                            console.error('DataTables request failed: ' + err.message);
                            reportAjaxError(pageUrl, err);
                        });
                    }
                }
            });

            if (typeof config.options === 'function') {
                dtOpts = config.options(dtOpts);
            }

            //Choose the column where the className contains "select-column" and apply the select extension to its render field
            //Added for Part-DB
            for (let column of dtOpts.columns) {
                if (column.className && column.className.includes('dt-select')) {
                    column.render = DataTable.render.select();
                }
            }

            root.innerHTML = data.template;
            dt = new DataTable(root.querySelector('table'), dtOpts);
            if (config.state !== 'none') {
                dt.on('draw.dt', function(e) {
                    var data = param(dt.state()).split('&');

                    // First draw establishes state, subsequent draws run diff on the first
                    if (!baseState) {
                        baseState = data;
                    } else {
                        var diff = data.filter(el => { return baseState.indexOf(el) === -1 && el.indexOf('time=') !== 0; });
                        switch (config.state) {
                            case 'fragment':
                                history.replaceState(null, null, window.location.origin + window.location.pathname + window.location.search
                                    + '#' + decodeURIComponent(diff.join('&')));
                                break;
                            case 'query':
                                history.replaceState(null, null, window.location.origin + window.location.pathname
                                    + '?' + decodeURIComponent(diff.join('&') + window.location.hash));
                                break;
                        }
                    }
                })
            }

            fulfill(dt);
        }).catch(function(err) {
            console.error('DataTables request failed: ' + err.message);
            reportAjaxError(initialUrl, err);
            reject(err);
        });
    });
}

/**
 * Server-side export.
 */
initDataTables.exportBtnAction = function(exporterName, settings) {
    settings = Object.assign({}, defaults, settings);

    return function(e, dt) {
        const params = param(Object.assign({}, dt.ajax.params(), {'_dt': settings.name, '_exporter': exporterName}));

        // Credit: https://stackoverflow.com/a/23797348
        const xhr = new XMLHttpRequest();
        xhr.open(settings.method, settings.method === 'GET' ? (settings.url + '?' +  params) : settings.url, true);
        xhr.responseType = 'arraybuffer';
        xhr.onload = function () {
            if (this.status === 200) {
                let filename = "";
                const disposition = xhr.getResponseHeader('Content-Disposition');
                if (disposition && disposition.indexOf('attachment') !== -1) {
                    const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                    const matches = filenameRegex.exec(disposition);
                    if (matches != null && matches[1]) {
                        filename = matches[1].replace(/['"]/g, '');
                    }
                }

                const type = xhr.getResponseHeader('Content-Type');

                let blob;
                if (typeof File === 'function') {
                    try {
                        blob = new File([this.response], filename, { type: type });
                    } catch { /* Edge */ }
                }

                if (typeof blob === 'undefined') {
                    blob = new Blob([this.response], { type: type });
                }

                if (typeof window.navigator.msSaveBlob !== 'undefined') {
                    // IE workaround for "HTML7007: One or more blob URLs were revoked by closing the blob for which they were created. These URLs will no longer resolve as the data backing the URL has been freed."
                    window.navigator.msSaveBlob(blob, filename);
                }
                else {
                    const URL = window.URL || window.webkitURL;
                    const downloadUrl = URL.createObjectURL(blob);

                    if (filename) {
                        // use HTML5 a[download] attribute to specify filename
                        const a = document.createElement("a");
                        // safari doesn't support this yet
                        if (typeof a.download === 'undefined') {
                            window.location = downloadUrl;
                        }
                        else {
                            a.href = downloadUrl;
                            a.download = filename;
                            document.body.appendChild(a);
                            a.click();
                        }
                    }
                    else {
                        window.location = downloadUrl;
                    }

                    setTimeout(function() { URL.revokeObjectURL(downloadUrl); }, 100); // cleanup
                }
            }
        };

        xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
        xhr.send(settings.method === 'POST' ? params : null);
    }
};

initDataTables.defaults = defaults;
