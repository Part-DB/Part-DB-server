/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

import * as bootbox from "bootbox";

/**
 * If this class is imported the user is shown an error dialog if he calls an page via Turbo and an error is responded.
 * @type {ErrorHandlerHelper}
 */
class ErrorHandlerHelper {
    constructor() {
        console.log('Error Handler registered');

        //const content = document.getElementById('content');
        //It seems that the content element is unreliable for these events, so we use the document instead
        const content = document;
        //content.addEventListener('turbo:before-fetch-response', (event) => this.handleError(event));
        content.addEventListener('turbo:fetch-request-error', (event) => this.handleError(event));
        content.addEventListener('turbo:frame-missing', (event) => this.handleError(event));

        $(document).ajaxError(this.handleJqueryErrror.bind(this));
    }

    _showAlert(statusText, statusCode, location, responseHTML)
    {
        const httpStatusToText = {
            '200': 'OK',
            '201': 'Created',
            '202': 'Accepted',
            '203': 'Non-Authoritative Information',
            '204': 'No Content',
            '205': 'Reset Content',
            '206': 'Partial Content',
            '300': 'Multiple Choices',
            '301': 'Moved Permanently',
            '302': 'Found',
            '303': 'See Other',
            '304': 'Not Modified',
            '305': 'Use Proxy',
            '306': 'Unused',
            '307': 'Temporary Redirect',
            '400': 'Bad Request',
            '401': 'Unauthorized',
            '402': 'Payment Required',
            '403': 'Forbidden',
            '404': 'Not Found',
            '405': 'Method Not Allowed',
            '406': 'Not Acceptable',
            '407': 'Proxy Authentication Required',
            '408': 'Request Timeout',
            '409': 'Conflict',
            '410': 'Gone',
            '411': 'Length Required',
            '412': 'Precondition Required',
            '413': 'Request Entry Too Large',
            '414': 'Request-URI Too Long',
            '415': 'Unsupported Media Type',
            '416': 'Requested Range Not Satisfiable',
            '417': 'Expectation Failed',
            '418': 'I\'m a teapot',
            '429': 'Too Many Requests',
            '500': 'Internal Server Error',
            '501': 'Not Implemented',
            '502': 'Bad Gateway',
            '503': 'Service Unavailable',
            '504': 'Gateway Timeout',
            '505': 'HTTP Version Not Supported',
        };

        //If the statusText is empty, we use the status code as text
        if (!statusText) {
            statusText = httpStatusToText[statusCode];
        }

        //Create error text
        const title = statusText + ' (Status ' + statusCode + ')';

        let trimString = function (string, length) {
            return string.length > length ?
                string.substring(0, length) + '...' :
                string;
        };

        const short_location = trimString(location, 50);

        const alert = bootbox.alert(
            {
                size: 'large',
                message: function() {
                    let url = location;
                    let msg = `Error calling <a href="${url}">${short_location}</a>.<br>`;
                    msg += '<b>Try to reload the page or contact the administrator if this error persists.</b>';

                    msg += '<br><br><a class=\"btn btn-outline-secondary mb-2\" data-bs-toggle=\"collapse\" href=\"#iframe_div\" >' + 'View details' + "</a>";
                    msg += "<div class=\" collapse\" id='iframe_div'><iframe height='512' width='100%' id='error-iframe'></iframe></div>";

                    return msg;
                },
                title: title,
                callback: function () {
                    //Remove blur
                    $('#content').removeClass('loading-content');
                }

            });

        alert.init(function (){
            var dstFrame = document.getElementById('error-iframe');
            //@ts-ignore
            var dstDoc = dstFrame.contentDocument || dstFrame.contentWindow.document;
            dstDoc.write(responseHTML)
            dstDoc.close();
        });
    }

    handleJqueryErrror(event, jqXHR, ajaxSettings, thrownError)
    {
        //Ignore status 422 as this means a symfony validation error occured and we need to show it to user. This is no (unexpected) error.
        if (jqXHR.status === 422) {
            return;
        }

        this._showAlert(jqXHR.statusText, jqXHR.status, ajaxSettings.url, jqXHR.responseText);
    }

    handleError(event) {
        //Prevent default error handling
        event.preventDefault();

        const response = event.detail.response;

        //Ignore aborted requests.
        if (response.statusText === 'abort' || response.status == 0) {
            return;
        }

        //Ignore status 422 as this means a symfony validation error occured and we need to show it to user. This is no (unexpected) error.
        if (response.status == 422) {
            return;
        }


        if(!response.ok) {
            response.text().then(responseHTML => {
                this._showAlert(response.statusText, response.status, response.url, responseHTML);
            }).catch(err => {
                this._showAlert(response.statusText, response.status, response.url, '<pre>' + err + '</pre>');
            });
        }
    }
}

export default new ErrorHandlerHelper();