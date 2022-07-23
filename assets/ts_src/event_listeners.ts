/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

import {ajaxUI} from "./ajax_ui";
import "bootbox";
import "marked";
import * as marked from "marked";
import {parse} from "marked";
import * as ZXing from "@zxing/library";

/************************************
 *
 * In this file all the functions that has to be called using AjaxUIoperation are registered.
 * You can use AjaxUI:start and AjaxUI:reload events.
 *
 ***********************************/

// Add bootstrap treeview on divs with data-tree-data attribute
$(document).on("ajaxUI:start ajaxUI:reload", function() {
    $("[data-tree-data]").each(function(index, element) {
        let data = $(element).data('treeData');

        //@ts-ignore
        $(element).treeview({
            data: data,
            enableLinks: false,
            showIcon: false,
            showBorder: true,
            searchResultBackColor: '#ffc107',
            searchResultColor: '#000',
            showTags: true,
            //@ts-ignore
            wrapNode: true,
            //@ts-ignore
            tagsClass: 'badge badge-secondary badge-pill pull-right',
            expandIcon: "fas fa-plus fa-fw fa-treeview", collapseIcon: "fas fa-minus fa-fw fa-treeview",
            onNodeSelected: function(event, data) {
                if(data.href) {
                    ajaxUI.navigateTo(data.href);
                }
            }
        }).on('initialized', function() {
            $(this).treeview('collapseAll', { silent: true });
            let selected = $(this).treeview('getSelected');
            $(this).treeview('revealNode', [ selected, {silent: true } ]);

            //Implement searching if needed.
            if($(this).data('treeSearch')) {
                let _this = this;
                let $search = $($(this).data('treeSearch'));
                $search.on( 'input', function() {
                    $(_this).treeview('collapseAll', { silent: true });
                    $(_this).treeview('search', [$search.val()]);
                });
            }

            //Add tree expand and reduce buttons if needed.
            if($(this).data('treeReduce')) {
                let _this = this;
                let $btn = $($(this).data('treeReduce'));
                $btn.click(function () {
                    $(_this).treeview('collapseAll');
                });
            }
            if($(this).data('treeExpand')) {
                let _this = this;
                let $btn = $($(this).data('treeExpand'));
                $btn.click(function () {
                    $(_this).treeview('expandAll');
                });
            }
        });

    });
});

$(document).on("ajaxUI:start ajaxUI:reload", function() {
    $("[data-delete-form]").unbind('submit').submit(function (event) {
        event.preventDefault();

        let form = this;

        //Get the submit button
        let btn = document.activeElement;

        let title = $(this).data("title");
        let message = $(this).data("message");

        bootbox.confirm({
            message: message, title: title, callback: function (result) {
                //If the dialog was confirmed, then submit the form.
                if (result) {
                    ajaxUI.submitForm(form, btn);
                }
            }
        });

        return false;
    });

    //Register for forms with delete-buttons
    $("[data-delete-btn]").parents('form').unbind('submit').submit(function (event) {
        event.preventDefault();
        let form = this;
        //Get the submit button
        let btn = document.activeElement;

        let title = $(btn).data("title");
        let message = $(btn).data("message");

        //If not the button with the message was pressed, then simply submit the form.
        if(!btn.hasAttribute('data-delete-btn')) {
            ajaxUI.submitForm(form, btn);
        }

        bootbox.confirm({
            message: message, title: title, callback: function (result) {
                //If the dialog was confirmed, then submit the form.
                if (result) {
                    ajaxUI.submitForm(form, btn);
                }
            }
        });

    });

});

$(document).on("ajaxUI:start ajaxUI:reload", function() {
    //@ts-ignore
    $(".tristate").tristate( {
        checked:            "true",
        unchecked:          "false",
        indeterminate:      "indeterminate",
    });

    $('.permission_multicheckbox:checkbox').change(function() {
        //Find the other checkboxes in this row, and change their value
        var $row = $(this).parents('tr');

        //@ts-ignore
        var new_state = $(this).tristate('state');

        //@ts-ignore
        $('.tristate:checkbox', $row).tristate('state', new_state);
    });
});

//Re initialize fileinputs on reload
$(document).on("ajaxUI:reload", function () {
    //@ts-ignore
    $(".file").fileinput();
});


/**
 * This listener keeps track of which tab is currently selected (using hash and localstorage) and will try to open
 * that tab on reload. That means that if the user changes something, he does not have to switch back to the tab
 * where he was before submit.
 */
$(document).on("ajaxUI:reload ajaxUI:start", function () {
    //Determine which tab should be shown (use hash if specified, otherwise use localstorage)
    var $activeTab = null;
    if (location.hash) {
        $activeTab = $('a[href=\'' + location.hash + '\']');
    } else if(localStorage.getItem('activeTab')) {
        $activeTab = $('a[href="' + localStorage.getItem('activeTab') + '"]');
    }

    if($activeTab) {
        //Findout if the tab has any parent tab we have to show before
        var parents = $($activeTab).parents('.tab-pane');
        parents.each(function(n) {
            $('a[href="#' + $(this).attr('id') + '"]').tab('show');
        });
        //Finally show the active tab itself
        $activeTab.tab('show');
    }

    $('body').on('click', 'a[data-toggle=\'tab\']', function (e) {
        e.preventDefault()
        var tab_name = this.getAttribute('href')
        if (history.replaceState) {
            history.replaceState(null, null, tab_name)
        }
        else {
            location.hash = tab_name
        }
        localStorage.setItem('activeTab', tab_name)

        $(this).tab('show');
        return false;
    });
});

/**
 * Load the higher resolution version of hover pictures.
 */
$(document).on("ajaxUI:reload ajaxUI:start ajaxUI:dt_loaded", function () {

    $('.hoverpic[data-thumbnail]').popover({
        html: true,
        trigger: 'hover',
        placement: 'right',
        container: 'body',
        content: function () {
            return '<img class="img-fluid" src="' + $(this).data('thumbnail') + '" />';
        }
    });
});


//Register typeaheads
$(document).on("ajaxUI:reload ajaxUI:start attachment:create", function () {
    $('input[data-autocomplete]').each(function() {
        //@ts-ignore
        var engine = new Bloodhound({
            //@ts-ignore
            datumTokenizer: Bloodhound.tokenizers.obj.whitespace(''),
            //@ts-ignore
            queryTokenizer: Bloodhound.tokenizers.obj.whitespace(''),
            remote: {
                url: $(this).data('autocomplete'),
                wildcard: 'QUERY'
            }
        });

        //@ts-ignore
        $(this).typeahead({
                hint: true,
                highlight: true,
                minLength: 1
            },
            {
                name: 'states',
                source: engine,
                limit: 250,
                templates: {
                    suggestion: function(data) {
                        if (typeof data === "string") {
                            return "<div>" + data + "</div>";
                        } else if(typeof data === "object" && typeof data.image === "string") {
                            return "<div class='row m-0'><div class='col-2 pl-0 pr-1'><img class='typeahead-image' src='" + data.image + "'/></div><div class='col-10'>" + data.name + "</div></div>"
                        }
                    },
                },
                display: 'name',
            });

        //Make the typeahead input fill the container (remove block-inline attr)
        $(this).parent(".twitter-typeahead").css('display', 'block');
    })
});


$(document).on("ajaxUI:start ajaxUI:reload attachment:create", function() {
    let updater = function() {
        //@ts-ignore
        let selected_option = $(this)[0].selectedOptions[0];
        let filter_string =  $(selected_option).data('filetype_filter');
        //Find associated file input

        let $row = $(this).parents('tr');
        //Set accept filter
        $('input[type="file"]', $row).prop('accept', filter_string);
    };

    //Register a change handler on all change listeners, and update it when the events are triggered
    $('select.attachment_type_selector').change(updater).each(updater);
});


$(document).on("ajaxUI:start ajaxUI:reload", function() {
    function setTooltip(btn, message) {
        $(btn).tooltip('hide')
            .attr('data-original-title', message)
            .tooltip('show');
    }

    function hideTooltip(btn) {
        setTimeout(function() {
            $(btn).tooltip('hide');
        }, 1000);
    }

    //@ts-ignore
    var clipboard = new ClipboardJS('.btn[data-clipboard-target], .btn[data-clipboard-text], .btn[data-clipboard-action]');
    clipboard.on('success', function(e) {
        setTooltip(e.trigger, 'Copied!');
        hideTooltip(e.trigger);
    });

    clipboard.on('error', function(e) {
        setTooltip(e.trigger, 'Failed!');
        hideTooltip(e.trigger);
    });
});

//Register U2F on page reload too...
$(document).on("ajaxUI:reload", function() {
    //@ts-ignore
    window.u2fauth.ready(function () {
        const form = document.getElementById('u2fForm')
        if (!form) {
            return
        }
        const type = form.dataset.action

        if (type === 'auth') {
            //@ts-ignore
            u2fauth.authenticate()
        } else if (type === 'reg' && form.addEventListener) {
            form.addEventListener('submit', function (event) {
                event.preventDefault()
                //@ts-ignore
                u2fauth.register()
            }, false)
        }
    })
});

//Need for proper body padding, with every navbar height
$(window).resize(function () {
    let height : number = $('#navbar').height() + 10;
    $('body').css('padding-top', height);
    $('#fixed-sidebar').css('top', height);
});

$(window).on('load', function () {
    let height : number = $('#navbar').height() + 10;
    $('body').css('padding-top', height);
    $('#fixed-sidebar').css('top', height);
});