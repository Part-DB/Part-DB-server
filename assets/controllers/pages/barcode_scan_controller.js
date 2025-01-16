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

import {Controller} from "@hotwired/stimulus";
//import * as ZXing from "@zxing/library";

import {Html5QrcodeScanner, Html5Qrcode} from "@part-db/html5-qrcode";

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
            fps: 10,
            qrbox: qrboxFunction,
            experimentalFeatures: {
                //This option improves reading quality on android chrome
                useBarCodeDetectorIfSupported: true
            }
        }, false);

        this._scanner.render(this.onScanSuccess.bind(this));
    }

    disconnect() {
        this._scanner.pause();
        this._scanner.clear();
    }

    onScanSuccess(decodedText, decodedResult) {
        //Put our decoded Text into the input box
        document.getElementById('scan_dialog_input').value = decodedText;
        //Submit form
        document.getElementById('scan_dialog_form').submit();
    }
}