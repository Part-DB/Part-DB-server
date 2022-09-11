import * as bootbox from "bootbox";

/**
 * If this class is imported the user is shown an error dialog if he calls an page via Turbo and an error is responded.
 * @type {ErrorHandlerHelper}
 */
class ErrorHandlerHelper {
    constructor() {
        console.log('Error Handler registered');

        const content = document.getElementById('content');
        content.addEventListener('turbo:before-fetch-response', (event) => this.handleError(event));

        $(document).ajaxError(this.handleJqueryErrror.bind(this));
    }

    _showAlert(statusText, statusCode, location, responseHTML)
    {
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
        const fetchResponse = event.detail.fetchResponse;
        const response = fetchResponse.response;

        //Ignore aborted requests.
        if (response.statusText === 'abort' || response.status == 0) {
            return;
        }

        //Ignore status 422 as this means a symfony validation error occured and we need to show it to user. This is no (unexpected) error.
        if (response.status == 422) {
            return;
        }

        if(fetchResponse.failed) {
            response.text().then(responseHTML => {
                this._showAlert(response.statusText, response.status, fetchResponse.location.toString(), responseHTML);
            }).catch(err => {
                this._showAlert(response.statusText, response.status, fetchResponse.location.toString(), '<pre>' + err + '</pre>');
            });
        }
    }
}

export default new ErrorHandlerHelper();