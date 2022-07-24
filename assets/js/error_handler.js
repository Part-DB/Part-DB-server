import * as bootbox from "bootbox";

/**
 * If this class is imported the user is shown an error dialog if he calls an page via Turbo and an error is responded.
 * @type {ErrorHandlerHelper}
 */
const ErrorHandlerHelper = class {
    constructor() {
        console.log('Error Handler registered');

        const content = document.getElementById('content');
        content.addEventListener('turbo:before-fetch-response', (event) => this.handleError(event));
    }

    handleError(event) {
        const fetchResponse = event.detail.fetchResponse;
        const response = fetchResponse.response;

        //Ignore aborted requests.
        if (response.statusText =='abort' || response.status == 0) {
            return;
        }

        if(fetchResponse.failed) {
            //Create error text
            let title = response.statusText + ' (Status ' + response.status + ')';

            /**
            switch(response.status) {
                case 500:
                    title =  'Internal Server Error!';
                    break;
                case 404:
                    title = "Site not found!";
                    break;
                case 403:
                    title = "Permission denied!";
                    break;
            } **/

            const alert = bootbox.alert(
                {
                    size: 'large',
                    message: function() {
                        let url = fetchResponse.location.toString();
                        let msg = `Error calling <a href="${url}">${url}</a>. `;
                        msg += 'Try to reload the page or contact the administrator if this error persists.'

                        msg += '<br><br><a class=\"btn btn-link\" data-bs-toggle=\"collapse\" href=\"#iframe_div\" >' + 'View details' + "</a>";
                        msg += "<div class=\" collapse\" id='iframe_div'><iframe height='512' width='100%' id='error-iframe'></iframe></div>";

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
                response.text().then( (html) => {
                    var dstFrame = document.getElementById('error-iframe');
                    //@ts-ignore
                    var dstDoc = dstFrame.contentDocument || dstFrame.contentWindow.document;
                    dstDoc.write(html)
                    dstDoc.close();
                });
            });
        }
    }
}

export default new ErrorHandlerHelper();