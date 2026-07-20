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

import Swal from "../helpers/swal";

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

        const userFriendlyMessages = {
            '400': 'The request was invalid or malformed.',
            '401': 'You need to log in to access this resource.',
            '403': 'You don\'t have permission to access this resource.',
            '404': 'The requested page or resource could not be found.',
            '408': 'The request timed out. Please check your connection and try again.',
            '409': 'There was a conflict with the current state of the resource.',
            '429': 'Too many requests sent. Please wait a moment and try again.',
            '500': 'An internal server error occurred. This is not your fault.',
            '502': 'The server received an invalid response from an upstream service.',
            '503': 'The service is temporarily unavailable. Please try again later.',
            '504': 'The server did not respond in time. Please try again later.',
        };

        if (!statusText) {
            statusText = httpStatusToText[String(statusCode)] ?? 'Unknown Error';
        }

        const title = `${statusText} <small class="text-muted fs-6">(HTTP ${statusCode})</small>`;
        const friendlyMsg = userFriendlyMessages[String(statusCode)]
            ?? 'An unexpected error occurred. Please try again or contact the administrator.';

        const short_location = location.length > 80
            ? location.substring(0, 80) + '…'
            : location;

        const msg = `
            <p class="mb-3">${friendlyMsg}</p>
            <p class="text-muted small mb-3">If this error keeps happening, please contact your administrator.</p>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#swal-error-details" aria-expanded="false">
                <i class="fas fa-code me-1"></i>Technical details
            </button>
            <div class="collapse mt-2" id="swal-error-details">
                <iframe height="400" width="100%" id="error-iframe" style="border:1px solid var(--bs-border-color);border-radius:var(--bs-border-radius);"></iframe>
            </div>`;

        const footer = `<span class="text-muted small">Error while loading: <a href="${location}" class="text-muted text-decoration-none" style="opacity:0.7;">${short_location}</a></span>`;

        Swal.fire({
            icon: 'error',
            title: title,
            html: msg,
            footer: footer,
            width: '90%',
            confirmButtonText: '<i class="fas fa-rotate-right me-1"></i>Reload page',
            showCancelButton: true,
            cancelButtonText: 'Close',
            showCloseButton: true,
            reverseButtons: true,
            didOpen: () => {
                const dstFrame = document.getElementById('error-iframe');
                //@ts-ignore
                const dstDoc = dstFrame.contentDocument || dstFrame.contentWindow.document;
                dstDoc.write(responseHTML);
                dstDoc.close();
            },
        }).then((result) => {
            document.getElementById('content').classList.remove('loading-content');
            if (result.isConfirmed) {
                window.location.reload();
            }
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

        //Skip 404 errors, on admin pages (as this causes a popup on deletion in firefox)
        if (response.status == 404 && event.target.id === 'admin-content-frame') {
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
