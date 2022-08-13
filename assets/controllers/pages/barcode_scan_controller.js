import {Controller} from "@hotwired/stimulus";
//import * as ZXing from "@zxing/library";

import {Html5QrcodeScanner, Html5Qrcode} from "html5-qrcode";

/* stimulusFetch: 'lazy' */
export default class extends Controller {

    //codeReader = null;

    _scanner = null;


    connect() {
        console.log('Init Scanner');

        //This function ensures, that the qrbox is 70% of the total viewport
        let qrboxFunction = function(viewfinderWidth, viewfinderHeight) {
            let minEdgePercentage = 0.7; // 70%
            let minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
            let qrboxSize = Math.floor(minEdgeSize * minEdgePercentage);
            return {
                width: qrboxSize,
                height: qrboxSize
            };
        }

        //Try to get the number of cameras. If the number is 0, then the promise will fail, and we show the warning dialog
        Html5Qrcode.getCameras().catch((devices) => {
                document.getElementById('scanner-warning').classList.remove('d-none');
        });

        this._scanner = new Html5QrcodeScanner(this.element.id, {
            fps: 2,
            qrbox: qrboxFunction,
            experimentalFeatures: {
                //This option improves reading quality on android chrome
                useBarCodeDetectorIfSupported: true
            }
        }, false);

        this._scanner.render(this.onScanSuccess.bind(this));
    }

    onScanSuccess(decodedText, decodedResult) {
        //Put our decoded Text into the input box
        document.getElementById('scan_dialog_input').value = decodedText;
        //Submit form
        document.getElementById('scan_dialog_form').submit();
    }
}