/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony)
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 *
 */

import {ajaxUI} from "./ajax_ui";
import "bootbox";
import "marked";
import * as marked from "marked";
import "qrcode";
import {parse} from "marked";

/************************************
 *
 * In this file all the functions that has to be called using AjaxUIoperation are registered.
 * You can use AjaxUI:start and AjaxUI:reload events.
 *
 ***********************************/


//Register greek input in search fields.
$(document).on("ajaxUI:start ajaxUI:reload", function() {
    //@ts-ignore
    $("input[type=text], textarea, input[type=search]").unbind("keydown").keydown(function (event : KeyboardEvent) {
        let greek = event.altKey;

        let greek_char : string = "";
        if (greek){
            switch(event.key) {
                case "w": //Omega
                    greek_char = '\u2126';
                    break;
                case "u":
                case "m": //Micro
                    greek_char = "\u00B5";
                    break;
                case "p": //Phi
                    greek_char = "\u03C6";
                    break;
                case "a": //Alpha
                    greek_char = "\u03B1";
                    break;
                case "b": //Beta
                    greek_char = "\u03B2";
                    break;
                case "c": //Gamma
                    greek_char = "\u03B3";
                    break;
                case "d": //Delta
                    greek_char = "\u03B4";
                    break;
                case "l": //Pound
                    greek_char = "\u00A3";
                    break;
                case "y": //Yen
                    greek_char = "\u00A5";
                    break;
                case "o": //Yen
                    greek_char = "\u00A4";
                    break;
                case "1": //Sum symbol
                    greek_char = "\u2211";
                    break;
                case "2": //Integral
                    greek_char = "\u222B";
                    break;
                case "3": //Less-than or equal
                    greek_char = "\u2264";
                    break;
                case "4": //Greater than or equal
                    greek_char = "\u2265";
                    break;
                case "5": //PI
                    greek_char = "\u03c0";
                    break;
                case "q": //Copyright
                    greek_char = "\u00A9";
                    break;
                case "e": //Euro
                    greek_char = "\u20AC";
                    break;
            }

            if(greek_char=="") return;

            let $txt = $(this);
            //@ts-ignore
            let caretPos = $txt[0].selectionStart;
            let textAreaTxt = $txt.val().toString();
            $txt.val(textAreaTxt.substring(0, caretPos) + greek_char + textAreaTxt.substring(caretPos) );

        }
    });
    //@ts-ignore
    this.greek_once = true;
});

//Register bootstrap select picker
$(document).on("ajaxUI:reload", function () {
    //@ts-ignore
    $(".selectpicker").selectpicker();
});

//Use bootstrap tooltips for the most tooltips
$(document).on("ajaxUI:start ajaxUI:reload ajaxUI:dt_loaded", function () {
    $(".tooltip").remove();
    $('a[title], button[title], span[title], h6[title], i.fas[title]')
    //@ts-ignore
        .tooltip("hide").tooltip({container: "body", placement: "auto", boundary: 'window'});
});

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

$(document).on("ajaxUI:start ajaxUI:reload", function () {
    $('input.tagsinput').each(function() {

        //Use typeahead if an autocomplete url was specified.
        if($(this).data('autocomplete')) {

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
            $(this).tagsinput({
                typeaheadjs: {
                    name: 'tags',
                    source: engine.ttAdapter()
                }
            });


        } else { //Init tagsinput without typeahead
            //@ts-ignore
            $(this).tagsinput();
        }
    })
});

/**
 * Register the button, to jump to the top of the page.
 */
$(document).on("ajaxUI:start", function registerJumpToTop() {
    $(window).scroll(function () {
        if ($(this).scrollTop() > 50) {
            $('#back-to-top').fadeIn();
        } else {
            $('#back-to-top').fadeOut();
        }
    });
    // scroll body to 0px on click
    $('#back-to-top').click(function () {
        $('#back-to-top').tooltip('hide');
        $('body,html').animate({
            scrollTop: 0
        }, 800);
        return false;
    }).tooltip();
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
        if (history.pushState) {
            history.pushState(null, null, tab_name)
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
    $(".hoverpic[data-thumbnail]").mouseenter(function() {
        $(this).attr('src', $(this).data('thumbnail'));
    });
});

/*
 * Register the button which is used to 
 */
$(document).on("ajaxUI:start", function() {
    let $sidebar = $("#fixed-sidebar");
    let $container = $("#main");
    let $toggler = $('#sidebar-toggle-button');

    function sidebarHide() {
        $sidebar.hide();
        $container.removeClass('col-md-9 col-lg-10 offset-md-3 offset-lg-2');
        $container.addClass('col-12');
        $toggler.html('<i class="fas fa-angle-right"></i>');
        $toggler.data('hidden', true);
        localStorage.setItem('sidebarHidden', 'true');
    }
    function sidebarShow() {
        let $sidebar = $("#fixed-sidebar");
        $sidebar.show();
        let $container = $("#main");
        $container.removeClass('col-12');
        $container.addClass('col-md-9 col-lg-10 offset-md-3 offset-lg-2');
        $toggler.html('<i class="fas fa-angle-left"></i>');
        $toggler.data('hidden', false);
        localStorage.setItem('sidebarHidden', 'false');
    }

    //Make the state persistent over reloads
    if(localStorage.getItem('sidebarHidden') === 'true') {
        sidebarHide();
    }

    //Register handler
    $toggler.click(function() {
        if($(this).data('hidden')) {
            sidebarShow();
        } else {
            sidebarHide();
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

        $(this).typeahead({
                hint: true,
                highlight: true,
                minLength: 1
            },
            {
                name: 'states',
                source: engine
            });

        //Make the typeahead input fill the container (remove block-inline attr)
        $(this).parent(".twitter-typeahead").css('display', 'block');
    })
});

$(document).on("ajaxUI:start", function () {
    function decodeHTML(html) {
        var txt = document.createElement('textarea');
        txt.innerHTML = html;
        return txt.value;
    }

    function parseMarkdown() {
        $('.markdown').each(function() {
            let unescaped = marked(decodeHTML( $(this).data('markdown')));
            //@ts-ignore
            let escaped = DOMPurify.sanitize(unescaped);
            $(this).html(escaped);
            //Remove markdown from DOM
            $(this).removeAttr('data-markdown');

            //Make all links external
            $('a', this).addClass('link-external').attr('target', '_blank').attr('rel', 'noopener');
            //Bootstrapify objects
            $('table', this).addClass('table table-hover table-striped table-bordered');
        });
    }

    //Configure markdown
    marked.setOptions({
        gfm: true,
    });

    parseMarkdown();
    $(document).on("ajaxUI:reload", parseMarkdown);
    $(document).on("ajaxUI:dt_loaded", parseMarkdown);
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
    $('.qrcode').each(function() {
        let canvas = $(this);
        //@ts-ignore
        QRCode.toCanvas(canvas[0], canvas.data('content'), function(error) {
            if(error) console.error(error);
        })
    });
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
    var clipboard = new ClipboardJS('.btn');
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