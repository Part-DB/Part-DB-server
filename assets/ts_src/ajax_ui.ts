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

/**
 * Extract the title (The name between the <title> tags) of a HTML snippet.
 * @param {string} html The HTML code which should be searched.
 * @returns {string} The title extracted from the html.
 */
function extractTitle(html : string) : string {
    let title : string = "";
    let regex = /<title>(.*?)<\/title>/gi;
    if (regex.test(html)) {
        let matches = html.match(regex);
        for(let match in matches) {
            title = $(matches[match]).text();
        }
    }
    return title;
}


class AjaxUI {

    protected BASE = "/";

    private trees_filled : boolean = false;

    private statePopped : boolean = false;

    public xhr : XMLHttpRequest;

    public constructor()
    {
        //Make back in the browser go back in history
        window.onpopstate = this.onPopState;
        $(document).ajaxError(this.onAjaxError.bind(this));
        //$(document).ajaxComplete(this.onAjaxComplete.bind(this));
    }

    /**
     * Starts the ajax ui und execute handlers registered in addStartAction().
     * Should be called in a document.ready, after handlers are set.
     */
    public start(disabled : boolean = false)
    {
        if(disabled) {
            return;
        }

        console.info("AjaxUI started!");

        this.BASE = $("body").data("base-url");
        //If path doesn't end with slash, add it.
        if(this.BASE[this.BASE.length - 1] !== '/') {
            this.BASE = this.BASE + '/';
        }
        console.info("Base path is " + this.BASE);

        //Show flash messages
        $(".toast").toast('show');


        /**
         * Save the XMLHttpRequest that jQuery used, to the class, so we can acess the responseURL property.
         * This is a work-around as long jQuery does not implement this property in its jQXHR objects.
         */
        //@ts-ignore
        jQuery.ajaxSettings.xhr = function () {
            //@ts-ignore
            let xhr = new window.XMLHttpRequest();
            //Save the XMLHttpRequest to the class.
            ajaxUI.xhr = xhr;
            return xhr;
        };


        this.registerLinks();
        this.registerForm();
        this.fillTrees();

        this.initDataTables();

        //Trigger start event
        $(document).trigger("ajaxUI:start");
    }

    /**
     * Fill the trees with the given data.
     */
    public fillTrees()
    {
        let categories =  localStorage.getItem("tree_datasource_tree-categories");
        let devices =  localStorage.getItem("tree_datasource_tree-devices");
        let tools =  localStorage.getItem("tree_datasource_tree-tools");

        if(categories == null) {
            categories = "categories";
        }

        if(devices == null) {
            devices = "devices";
        }

        if(tools == null) {
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
            let mode = $(this).data("mode");
            let target = $(this).data("target");
            let text = $(this).text() + " \n<span class='caret'></span>"; //Add caret or it will be removed, when written into title

            if (mode==="collapse") {
                // @ts-ignore
                $('#' + target).treeview('collapseAll', { silent: true });
            }
            else if(mode==="expand") {
                // @ts-ignore
                $('#' + target).treeview('expandAll', { silent: true });
            } else {
                localStorage.setItem("tree_datasource_" + target, mode);
                ajaxUI.treeLoadDataSource(target, mode);
            }

            return false;
        });
    }

    /**
     * Load the given url into the tree with the given id.
     * @param target_id
     * @param datasource
     */
    protected treeLoadDataSource(target_id, datasource) {
        let text : string = $(".tree-btns[data-mode='" + datasource + "']").html();
        text = text + " \n<span class='caret'></span>"; //Add caret or it will be removed, when written into title
        switch(datasource) {
            case "categories":
                ajaxUI.initTree("#" + target_id, 'tree/categories');
                break;
            case "locations":
                ajaxUI.initTree("#" + target_id, 'tree/locations');
                break;
            case "footprints":
                ajaxUI.initTree("#" + target_id, 'tree/footprints');
                break;
            case "manufacturers":
                ajaxUI.initTree("#" + target_id, 'tree/manufacturers');
                break;
            case "suppliers":
                ajaxUI.initTree("#" + target_id, 'tree/suppliers');
                break;
            case "tools":
                ajaxUI.initTree("#" + target_id, 'tree/tools');
                break;
            case "devices":
                ajaxUI.initTree("#" + target_id, 'tree/devices');
                break;
        }

        $( "#" + target_id + "-title").html(text);
    }

    /**
     * Fill a treeview with data from the given url.
     * @param tree The Jquery selector for the tree (e.g. "#tree-tools")
     * @param url The url from where the data should be loaded
     */
    public initTree(tree, url) {
        //let contextmenu_handler = this.onNodeContextmenu;
        $.getJSON(ajaxUI.BASE + url, function (data) {
            // @ts-ignore
            $(tree).treeview({
                data: data,
                enableLinks: false,
                showIcon: false,
                showBorder: true,
                searchResultBackColor: '#ffc107',
                searchResultColor: '#000',
                onNodeSelected: function(event, data) {
                    if(data.href) {
                        ajaxUI.navigateTo(data.href);
                    }
                },
                //onNodeContextmenu: contextmenu_handler,
                expandIcon: "fas fa-plus fa-fw fa-treeview", collapseIcon: "fas fa-minus fa-fw fa-treeview"})
                .on('initialized', function() {
                    $(this).treeview('collapseAll', { silent: true });

                    //Implement searching if needed.
                    if($(this).data('treeSearch')) {
                        let _this = this;
                        let $search = $($(this).data('treeSearch'));
                        $search.on( 'input', function() {
                            $(_this).treeview('collapseAll', { silent: true });
                            $(_this).treeview('search', [$search.val()]);
                        });
                    }
                });
        });
    }


    /**
     * Register all links, for loading via ajax.
     */
    public registerLinks()
    {
        // Unbind all old handlers, so the things are not executed multiple times.
        $('a').not(".link-external, [data-no-ajax], .page-link, [href^='javascript'], [href^='#']").unbind('click').click(function (event) {
                let a = $(this);
                let href = $.trim(a.attr("href"));
                //Ignore links without href attr and nav links ('they only have a #)
                if(href != null && href != "" && href.charAt(0) !== '#') {
                    event.preventDefault();
                    ajaxUI.navigateTo(href);
                }
            }
        );
        console.debug('Links registered!');
    }

    protected getFormOptions() : JQueryFormOptions
    {
        return  {
            success: this.onAjaxComplete,
            beforeSerialize: function($form, options) : boolean {

                //Update the content of textarea fields using CKEDITOR before submitting.
                //@ts-ignore
                if(typeof CKEDITOR !== 'undefined') {
                    //@ts-ignore
                    for (let name in CKEDITOR.instances) {
                        //@ts-ignore
                        CKEDITOR.instances[name].updateElement();
                    }
                }

                //Check every checkbox field, so that it will be submitted (only valid fields are submitted)
                $form.find("input[type=checkbox].tristate").prop('checked', true);

                return true;
            },
            beforeSubmit: function (arr, $form, options) : boolean {
                //When data-with-progbar is specified, then show progressbar.
                if($form.data("with-progbar") != undefined) {
                    ajaxUI.showProgressBar();
                }
                return true;
            }
        };
    }

    /**
     * Register all forms for loading via ajax.
     */
    public registerForm()
    {

        let options = this.getFormOptions();

        $('form').not('[data-no-ajax]').ajaxForm(options);

        console.debug('Forms registered!');
    }


    /**
     * Submits the given form via ajax.
     * @param form The form that will be submmitted.
     * @param btn The btn via which the form is submitted
     */
    public submitForm(form, btn = null)
    {
        let options = ajaxUI.getFormOptions();

        if(btn) {
            options.data = {};
            options.data[$(btn).attr('name')] = $(btn).attr('value');
        }

        $(form).ajaxSubmit(options);
    }


    /**
     * Show the progressbar
     */
    public showProgressBar()
    {
        //Blur content background
        $('#content').addClass('loading-content');

        // @ts-ignore
        $('#progressModal').modal({
            keyboard: false,
            backdrop: false,
            show: true
        });
    }

    /**
     * Hides the progressbar.
     */
    public hideProgressBar()
    {
        // @ts-ignore
        $('#progressModal').modal('hide');
        //Remove the remaining things of the modal
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        $('body, .navbar').css('padding-right', "");

    }


    /**
     * Navigates to the given URL
     * @param url The url which should be opened.
     * @param show_loading Show the loading bar during loading.
     */
    public navigateTo(url : string, show_loading : boolean = true)
    {
        if(show_loading) {
            this.showProgressBar();
        }
        $.ajax(url, {
            success: this.onAjaxComplete
        });
        //$.ajax(url).promise().done(this.onAjaxComplete);
    }

    /**
     * Called when an error occurs on loading ajax. Outputs the message to the console.
     */
    private onAjaxError (event, request, settings) {
        'use strict';
        //Ignore aborted requests.
        if (request.statusText =='abort') {
            return;
        }

        //Ignore ajax errors with 200 code (like the ones during 2FA authentication)
        if(request.status == 200) {
            return;
        }

        console.error("Error getting the ajax data from server!");
        console.log(event);
        console.log(request);
        console.log(settings);

        ajaxUI.hideProgressBar();

        //Create error text
        let title = "";

        switch(request.status) {
            case 500:
                title =  'Internal Server Error!';
                break;
            case 404:
                title = "Site not found!";
                break;
            case 403:
                title = "Permission denied!";
                break;
        }

        var alert = bootbox.alert(
            {
                size: 'large',
                message: function() {
                    let msg = "Error getting data from Server! <b>Status Code: " + request.status + "</b>";

                    msg += '<br><br><a class=\"btn btn-link\" data-toggle=\"collapse\" href=\"#iframe_div\" >' + 'Show response' + "</a>";
                    msg += "<div class=\" collapse\" id='iframe_div'><iframe height='512' width='100%' id='iframe'></iframe></div>";

                    return msg;
                },
                title: title,
                callback: function () {
                    //Remove blur
                    $('#content').removeClass('loading-content');
                }

            });

        //@ts-ignore
        alert.init(function (){
            var dstFrame = document.getElementById('iframe');
            //@ts-ignore
            var dstDoc = dstFrame.contentDocument || dstFrame.contentWindow.document;
            dstDoc.write(request.responseText);
            dstDoc.close();
        });



        //If it was a server error and response is not empty, show it to user.
        if(request.status == 500 && request.responseText !== "")
        {
            console.log("Response:" + request.responseText);
        }
    }

    /**
     * This function gets called every time, the "back" button in the browser is pressed.
     * We use it to load the content from history stack via ajax and to rewrite url, so we only have
     * to load #content-data
     * @param event
     */
    private onPopState(event)
    {
        let page : string = location.href;
        ajaxUI.statePopped = true;
        ajaxUI.navigateTo(page);
    }

    /**
     * This function takes the response of an ajax requests, and does the things we need to do for our AjaxUI.
     * This includes inserting the content and pushing history.
     * @param responseText
     * @param textStatus
     * @param jqXHR
     */
    private onAjaxComplete(responseText: string, textStatus: string, jqXHR: any)
    {
        console.debug("Ajax load completed!");


        ajaxUI.hideProgressBar();

        /* We need to do the url checking before the parseHTML, so that we dont get wrong url name, caused by scripts
           in the new content */
        // @ts-ignore
        let url = this.url;
        //Check if we were redirect to a new url, then we should use that as new url.
        if(ajaxUI.xhr.responseURL) {
            url = ajaxUI.xhr.responseURL;
        }


        //Parse response to DOM structure
        //We need to preserve javascript, so the table ca
        let dom = $.parseHTML(responseText, document, true);
        //And replace the content container
        $("#content").replaceWith($("#content", dom));
        //Replace login menu too (so everything is up to date)
        $("#login-content").replaceWith($('#login-content', dom));

        //Replace flash messages and show them
        $("#message-container").replaceWith($('#message-container', dom));
        $(".toast").toast('show');

        //Set new title
        let title  = extractTitle(responseText);
        document.title = title;

        //Push to history, if we currently arent poping an old value.
        if(!ajaxUI.statePopped) {
            history.pushState(null, title, url);
        } else {
            //Clear pop state
            ajaxUI.statePopped = false;
        }

        //Do things on the new dom
        ajaxUI.registerLinks();
        ajaxUI.registerForm();
        ajaxUI.initDataTables();

        //Trigger reload event
        $(document).trigger("ajaxUI:reload");
    }

    /**
     * Init all datatables marked with data-datatable based on their data-settings attribute.
     */
    protected initDataTables()
    {
        //@ts-ignore
        $($.fn.DataTable.tables()).DataTable().fixedHeader.disable();
        //@ts-ignore
        $($.fn.DataTable.tables()).DataTable().destroy();

        //Find all datatables and init it.
        let $tables = $('[data-datatable]');
        $.each($tables, function(index, table) {
            let $table = $(table);
            let settings = $table.data('settings');

            //@ts-ignore
            var promise = $('#part_list').initDataTables(settings,
                {
                    colReorder: true,
                    responsive: true,
                    "fixedHeader": { header: $(window).width() >= 768, //Only enable fixedHeaders on devices with big screen. Fixes scrolling issues on smartphones.
                        headerOffset: $("#navbar").height()},
                    "buttons": [ {
                        "extend": 'colvis',
                        'className': 'mr-2 btn-light',
                        "text": "<i class='fa fa-cog'></i>"
                    }],
                    "rowCallback": function( row, data, index ) {
                        //Check if we have a level, then change color of this row
                        if (data.level) {
                            let style = "";
                            switch(data.level) {
                                case "emergency":
                                case "alert":
                                case "critical":
                                case "error":
                                    style = "table-danger";
                                    break;
                                case "warning":
                                    style = "table-warning";
                                    break;
                                case "notice":
                                    style = "table-info";
                                    break;
                            }

                            if (style){
                                $(row).addClass(style);
                            }
                        }
                    }
                });

            //Register links.
            promise.then(function() {
                ajaxUI.registerLinks();

                //Set the correct title in the table.
                let title = $('#part-card-header-src');
                $('#part-card-header').html(title.html());
                $(document).trigger('ajaxUI:dt_loaded');

                //Attach event listener to update links after new page selection:
                $('#dt').on('draw.dt column-visibility.dt', function() {
                    ajaxUI.registerLinks();
                    $(document).trigger('ajaxUI:dt_loaded');
                });
            });
        });

        console.debug('Datatables inited.');
    }
}

export let ajaxUI = new AjaxUI();