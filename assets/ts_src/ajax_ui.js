"use strict";
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
Object.defineProperty(exports, "__esModule", { value: true });
var Cookies = require("js-cookie");
/**
 * Extract the title (The name between the <title> tags) of a HTML snippet.
 * @param {string} html The HTML code which should be searched.
 * @returns {string} The title extracted from the html.
 */
function extractTitle(html) {
    var title = "";
    var regex = /<title>(.*?)<\/title>/gi;
    if (regex.test(html)) {
        var matches = html.match(regex);
        for (var match in matches) {
            title = $(matches[match]).text();
        }
    }
    return title;
}
var AjaxUI = /** @class */ (function () {
    function AjaxUI() {
        this.BASE = "/";
        this.trees_filled = false;
        this.statePopped = false;
        //Make back in the browser go back in history
        window.onpopstate = this.onPopState;
        $(document).ajaxError(this.onAjaxError.bind(this));
        //$(document).ajaxComplete(this.onAjaxComplete.bind(this));
    }
    /**
     * Starts the ajax ui und execute handlers registered in addStartAction().
     * Should be called in a document.ready, after handlers are set.
     */
    AjaxUI.prototype.start = function () {
        console.info("AjaxUI started!");
        this.BASE = $("body").data("base-url") + "/";
        console.info("Base path is " + this.BASE);
        this.registerLinks();
        this.registerForm();
        this.fillTrees();
    };
    /**
     * Fill the trees with the given data.
     */
    AjaxUI.prototype.fillTrees = function () {
        var categories = Cookies.get("tree_datasource_tree-categories");
        var devices = Cookies.get("tree_datasource_tree-devices");
        var tools = Cookies.get("tree_datasource_tree-tools");
        if (typeof categories == "undefined") {
            categories = "categories";
        }
        if (typeof devices == "undefined") {
            devices = "devices";
        }
        if (typeof tools == "undefined") {
            tools = "tools";
        }
        this.treeLoadDataSource("tree-categories", categories);
        this.treeLoadDataSource("tree-devices", devices);
        this.treeLoadDataSource("tree-tools", tools);
        this.trees_filled = true;
        //Register tree btns to expand all, or to switch datasource.
        $(".tree-btns").click(function (event) {
            event.preventDefault();
            $(this).parents("div.dropdown").removeClass('show');
            //$(this).closest(".dropdown-menu").removeClass('show');
            $(".dropdown-menu.show").removeClass("show");
            var mode = $(this).data("mode");
            var target = $(this).data("target");
            var text = $(this).text() + " \n<span class='caret'></span>"; //Add caret or it will be removed, when written into title
            if (mode === "collapse") {
                // @ts-ignore
                $('#' + target).treeview('collapseAll', { silent: true });
            }
            else if (mode === "expand") {
                // @ts-ignore
                $('#' + target).treeview('expandAll', { silent: true });
            }
            else {
                Cookies.set("tree_datasource_" + target, mode);
                exports.ajaxUI.treeLoadDataSource(target, mode);
            }
            return false;
        });
    };
    /**
     * Load the given url into the tree with the given id.
     * @param target_id
     * @param datasource
     */
    AjaxUI.prototype.treeLoadDataSource = function (target_id, datasource) {
        var text = $(".tree-btns[data-mode='" + datasource + "']").html();
        text = text + " \n<span class='caret'></span>"; //Add caret or it will be removed, when written into title
        switch (datasource) {
            case "categories":
                exports.ajaxUI.initTree("#" + target_id, 'tree/categories/');
                break;
            case "locations":
                exports.ajaxUI.initTree("#" + target_id, 'tree/locations');
                break;
            case "footprints":
                exports.ajaxUI.initTree("#" + target_id, 'tree/footprints');
                break;
            case "manufacturers":
                exports.ajaxUI.initTree("#" + target_id, 'tree/manufacturers');
                break;
            case "suppliers":
                exports.ajaxUI.initTree("#" + target_id, 'tree/suppliers');
                break;
            case "tools":
                exports.ajaxUI.initTree("#" + target_id, 'tree/tools/');
                break;
            case "devices":
                exports.ajaxUI.initTree("#" + target_id, 'tree/devices');
                break;
        }
        $("#" + target_id + "-title").html(text);
    };
    /**
     * Fill a treeview with data from the given url.
     * @param tree The Jquery selector for the tree (e.g. "#tree-tools")
     * @param url The url from where the data should be loaded
     */
    AjaxUI.prototype.initTree = function (tree, url) {
        //let contextmenu_handler = this.onNodeContextmenu;
        $.getJSON(exports.ajaxUI.BASE + url, function (data) {
            // @ts-ignore
            $(tree).treeview({
                data: data,
                enableLinks: false,
                showIcon: false,
                showBorder: true,
                onNodeSelected: function (event, data) {
                    if (data.href) {
                        exports.ajaxUI.navigateTo(data.href);
                    }
                },
                //onNodeContextmenu: contextmenu_handler,
                expandIcon: "fas fa-plus fa-fw fa-treeview", collapseIcon: "fas fa-minus fa-fw fa-treeview"
            }).treeview('collapseAll', { silent: true });
        });
    };
    /**
     * Register all links, for loading via ajax.
     */
    AjaxUI.prototype.registerLinks = function () {
        $('a').not(".link-external, [data-no-ajax]").click(function (event) {
            var a = $(this);
            var href = $.trim(a.attr("href"));
            //Ignore links without href attr and nav links ('they only have a #)
            if (href != null && href != "" && href.charAt(0) !== '#') {
                event.preventDefault();
                exports.ajaxUI.navigateTo(href);
            }
        });
        console.debug('Links registered!');
    };
    /**
     * Register all forms for loading via ajax.
     */
    AjaxUI.prototype.registerForm = function () {
        var options = {
            success: this.onAjaxComplete,
            beforeSubmit: function (arr, $form, options) {
                //When data-with-progbar is specified, then show progressbar.
                if ($form.data("with-progbar") != undefined) {
                    exports.ajaxUI.showProgressBar();
                }
                return true;
            }
        };
        $('form').not('[data-no-ajax]').ajaxForm(options);
        console.debug('Forms registered!');
    };
    AjaxUI.prototype.showProgressBar = function () {
        //Blur content background
        $('#content').addClass('loading-content');
        // @ts-ignore
        $('#progressModal').modal({
            keyboard: false,
            backdrop: false,
            show: true
        });
    };
    AjaxUI.prototype.hideProgressBar = function () {
        // @ts-ignore
        $('#progressModal').modal('hide');
        //Remove the remaining things of the modal
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        $('body, .navbar').css('padding-right', "");
    };
    /**
     * Navigates to the given URL
     * @param url The url which should be opened.
     * @param show_loading Show the loading bar during loading.
     */
    AjaxUI.prototype.navigateTo = function (url, show_loading) {
        if (show_loading === void 0) { show_loading = true; }
        if (show_loading) {
            this.showProgressBar();
        }
        $.ajax(url, {
            success: this.onAjaxComplete
        });
        //$.ajax(url).promise().done(this.onAjaxComplete);
    };
    /**
     * Called when an error occurs on loading ajax. Outputs the message to the console.
     */
    AjaxUI.prototype.onAjaxError = function (event, request, settings) {
        'use strict';
        //Ignore aborted requests.
        if (request.statusText == 'abort') {
            return;
        }
        console.error("Error getting the ajax data from server!");
        console.log(event);
        console.log(request);
        console.log(settings);
        //If it was a server error and response is not empty, show it to user.
        if (request.status == 500 && request.responseText !== "") {
            console.log("Response:" + request.responseText);
        }
    };
    /**
     * This function gets called every time, the "back" button in the browser is pressed.
     * We use it to load the content from history stack via ajax and to rewrite url, so we only have
     * to load #content-data
     * @param event
     */
    AjaxUI.prototype.onPopState = function (event) {
        var page = location.href;
        exports.ajaxUI.statePopped = true;
        exports.ajaxUI.navigateTo(page);
    };
    /**
     * This function takes the response of an ajax requests, and does the things we need to do for our AjaxUI.
     * This includes inserting the content and pushing history.
     * @param responseText
     * @param textStatus
     * @param jqXHR
     */
    AjaxUI.prototype.onAjaxComplete = function (responseText, textStatus, jqXHR) {
        console.debug("Ajax load completed!");
        exports.ajaxUI.hideProgressBar();
        //Parse response to DOM structure
        var dom = $.parseHTML(responseText);
        //And replace the content container
        $("#content").replaceWith($("#content", dom));
        //Replace login menu too (so everything is up to date)
        $("#login-content").replaceWith($('#login-content', dom));
        //Replace flash messages and show them
        $("#message-container").replaceWith($('#message-container', dom));
        $(".toast").toast('show');
        //Set new title
        var title = extractTitle(responseText);
        document.title = title;
        //Push to history, if we currently arent poping an old value.
        if (!exports.ajaxUI.statePopped) {
            // @ts-ignore
            history.pushState(null, title, this.url);
        }
        else {
            //Clear pop state
            exports.ajaxUI.statePopped;
        }
        //Do things on the new dom
        exports.ajaxUI.registerLinks();
        exports.ajaxUI.registerForm();
    };
    return AjaxUI;
}());
exports.ajaxUI = new AjaxUI();
//# sourceMappingURL=ajax_ui.js.map