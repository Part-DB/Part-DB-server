/**
 * Symfony DataTables Bundle
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @author Niels Keurentjes <niels.keurentjes@omines.com>
 */

(function($) {
    /**
     * Initializes the datatable dynamically.
     */
    $.fn.initDataTables = function(config, options) {
        var root = this,
            config = $.extend({}, $.fn.initDataTables.defaults, config),
            state = ''
        ;

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
        var persistOptions = config.state === 'none' ? {} : {
            stateSave: true,
            stateLoadCallback: function(s, cb) {
                // Only need stateSave to expose state() function as loading lazily is not possible otherwise
                return null;
            }
        };

        return new Promise((fulfill, reject) => {
            // Perform initial load
            $.ajax(config.url, {
                method: config.method,
                data: {
                    _dt: config.name,
                    _init: true
                }
            }).done(function(data) {
                var baseState;

                // Merge all options from different sources together and add the Ajax loader
                var dtOpts = $.extend({}, data.options, config.options, options, persistOptions, {
                    ajax: function (request, drawCallback, settings) {
                        if (data) {
                            data.draw = request.draw;
                            drawCallback(data);
                            data = null;
                            if (Object.keys(state).length) {
                                var merged = $.extend(true, {}, dt.state(), state);
                                dt
                                    .order(merged.order)
                                    .search(merged.search.search)
                                    .page.len(merged.length)
                                    .page(merged.start / merged.length)
                                    .draw(false);
                            }
                        } else {
                            request._dt = config.name;
                            $.ajax(config.url, {
                                method: config.method,
                                data: request
                            }).done(function(data) {
                                drawCallback(data);
                            })
                        }
                    }
                });

                root.html(data.template);
                dt = $('table', root).DataTable(dtOpts);
                if (config.state !== 'none') {
                    dt.on('draw.dt', function(e) {
                        var data = $.param(dt.state()).split('&');

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
            }).fail(function(xhr, cause, msg) {
                console.error('DataTables request failed: ' + msg);
                reject(cause);
            });
        });
    };

    /**
     * Provide global component defaults.
     */
    $.fn.initDataTables.defaults = {
        method: 'POST',
        state: 'fragment',
        url: window.location.origin + window.location.pathname
    };

    /**
     * Convert a querystring to a proper array - reverses $.param
     */
    function deparam(params, coerce) {
        var obj = {},
            coerce_types = {'true': !0, 'false': !1, 'null': null};
        $.each(params.replace(/\+/g, ' ').split('&'), function (j, v) {
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
                    if ($.isArray(obj[key])) {
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
}($));
