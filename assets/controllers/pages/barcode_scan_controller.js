import {Controller} from "@hotwired/stimulus";
import * as ZXing from "@zxing/library";

/* stimulusFetch: 'lazy' */
export default class extends Controller {

    static targets = [ "source" ]

    codeReader = null;

    connect() {
        console.log('Init Scanner');
        this.codeReader = new ZXing.BrowserMultiFormatReader();
        this.initScanner();
    }

    codeScannedHandler(result, err) {
        if (result) {
            //@ts-ignore
            document.getElementById('scan_dialog_input').value = result.text;
            //Submit form
            //@ts-ignore
            document.getElementById('scan_dialog_form').submit();
        }
        if (err && !(err instanceof ZXing.NotFoundException)) {
            console.error(err);
            //document.getElementById('result').textContent = err
        }
    }

    initScanner() {
        let selectedDeviceId;

        this.codeReader.listVideoInputDevices()
            .then((videoInputDevices) => {
                if (videoInputDevices.length >= 1) {
                    const sourceSelect = document.getElementById('sourceSelect');


                    videoInputDevices.forEach((element) => {
                        const sourceOption = document.createElement('option');
                        sourceOption.text = element.label;
                        sourceOption.value = element.deviceId;
                        sourceSelect.appendChild(sourceOption);
                    });

                    //Try to retrieve last selected webcam...
                    let last_cam_id = localStorage.getItem('scanner_last_cam_id');
                    if (!!last_cam_id) {
                        //selectedDeviceId = localStorage.getItem('scanner_last_cam_id');
                        sourceSelect.value = last_cam_id;
                    } else {
                        selectedDeviceId = videoInputDevices[0].deviceId;
                    }

                    sourceSelect.onchange = () => {
                        //@ts-ignore
                        selectedDeviceId = sourceSelect.value;
                        localStorage.setItem('scanner_last_cam_id', selectedDeviceId);
                        changeHandler();
                    };

                    document.getElementById('sourceSelectPanel').classList.remove('d-none');
                    document.getElementById('video').classList.remove('d-none');
                    document.getElementById('scanner-warning').classList.add('d-none');
                }


                let changeHandler = () => {
                    this.codeReader.reset();
                    this.codeReader.decodeFromVideoDevice(selectedDeviceId, 'video', (result, err) => this.codeScannedHandler(result, err));
                    console.log(`Started continous decode from camera with id ${selectedDeviceId}`)
                };

                //Register Change Src Button
                //document.getElementById('changeSrcBtn').addEventListener('click', changeHandler);

                //Try to start logging automatically.
                changeHandler();

            })
            .catch((err) => {
                console.error(err)
            })
    }
}