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

import * as Cookies from "js-cookie";

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

    private trees_filled : boolean = false;

    private statePopped : boolean = false;

    public test()
    {
        alert("Test");
    }

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
    public start()
    {
        console.info("AjaxUI started!");
        this.registerLinks();
        this.registerForm();
    }

    /**
     * Register all links, for loading via ajax.
     */
    protected registerLinks()
    {
        $('a').not(".link-external, [data-no-ajax]").click(function (event) {
                let a = $(this);
                let href = $.trim(a.attr("href"));
                //Ignore links without href attr and nav links ('they only have a #)
                if(href != null && href != "" && href.charAt(0) !== '#') {
                    event.preventDefault();
                    ajaxUI.navigateTo(href);
                }
            }
        )
        console.debug('Links registered!');
    }

    /**
     * Register all forms for loading via ajax.
     */
    protected registerForm()
    {
        let options : JQueryFormOptions = {
            success: this.onAjaxComplete,
            beforeSubmit: function (arr, $form, options) : boolean {
                //When data-with-progbar is specified, then show progressbar.
                if($form.data("with-progbar") != undefined) {
                    ajaxUI.showProgressBar();
                }
                return true;
            }
        }
        $('form').not('[data-no-ajax]').ajaxForm(options);

        console.debug('Forms registered!');
    }


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

        console.log(event);
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

        //Parse response to DOM structure
        let dom = $.parseHTML(responseText);
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
            // @ts-ignore
            history.pushState(null, title, this.url);
        } else {
            //Clear pop state
            ajaxUI.statePopped;
        }

        //Do things on the new dom
        ajaxUI.registerLinks();
        ajaxUI.registerForm();


    }
}

export let ajaxUI = new AjaxUI();