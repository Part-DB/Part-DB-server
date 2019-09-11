/*
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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
$(document).on("ajaxUI:start ajaxUI:reload", function () {
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
    $("[data-delete-form]").unbind('submit').submit(function(event) {
        event.preventDefault();

        let form = this;

        let title = $(this).data("title");
        let message = $(this).data("message");

        bootbox.confirm({
            message: message,
            title: title,
            callback: function(result) {
            //If the dialog was confirmed, then submit the form.
            if(result) {
                ajaxUI.submitForm(form);
            }
        }});

        return false;
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
        $('.tristate:checkbox', $row).tristate('state', new_state;
    });
});

//Re initialize fileinputs on reload
$(document).on("ajaxUI:reload", function () {
    //@ts-ignore
    $(".file").fileinput();
});

$(document).on("ajaxUI:reload", function () {
    //@ts-ignore
    $("input[data-role='tagsinput']").tagsinput();
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