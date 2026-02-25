import {Controller} from "@hotwired/stimulus";
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2024 Alex Barclay (https://github.com/barclaac)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/* stimulusFetch: 'lazy' */
export default class extends Controller {

    _scanner = null;

    constructor() {
	super()
	_scanner = new ScannerSerial()
    }

    connect(event) {
	console.log('Controller connected')

	if (_scanner.isConnected()) {
	    console.log('already connected')
	    document.getElementById('handheld_scanner_dialog_connect').style.display='none'
	    document.getElementById('handheld_scanner_dialog_disconnect').style.display=''
	}
    }

    async onConnectScanner(event) {
	console.log('Connect to barcode reader')

	if (!_scanner.isConnected()) {
	    await _scanner.connect(async (reader) => {
		decoder = new TextDecoder();
		var barcodeBuffer = '';
		while (true) {
		    const { value, done } = await reader.read();
		    if (done) {
			console.log('releasing reader')
			reader.releaseLock();
			reader = null;
			break;
		    }
		    console.log(value);
		    console.log(decoder.decode(value));
		    partial = decoder.decode(value);
		    barcodeBuffer += partial
		    end = false
		    endidx = partial.indexOf('\x1e\x04');
		    if (endidx != -1) {
			end = true;
		    } else {
			endidx = partial.indexOf('\r');
			if (endidx != -1) {
			    end = true;
			}
		    }

		    if (end) {
			// Decode the barcode
			console.log(barcodeBuffer)
			start = barcodeBuffer.indexOf('[)>')
			if (start == -1) {
			    console.log('badly formed barcode')
			} else {
			    // Post this back to the server
			    document.getElementById('handheld_scanner_dialog_barcode').value = barcodeBuffer;
			    form = document.getElementById('handheld_dialog_form');
			    form.requestSubmit();
			}
			barcodeBuffer = '';
		    }
		}
	    })
	    document.getElementById('handheld_scanner_dialog_connect').style.display='none'
	    document.getElementById('handheld_scanner_dialog_disconnect').style.display=''

	}
    }

    async onDisconnectScanner(event) {
	console.log('Disconnect called')
	if (_scanner.isConnected) {
	    _scanner.disconnect()
	    document.getElementById('handheld_scanner_dialog_connect').style.display=''
	    document.getElementById('handheld_scanner_dialog_disconnect').style.display='none'
	}
    }
}

class ScannerSerial {
    device = null
    reader = null

    constructor() {
	if (ScannerSerial.instance) {
	    return ScannerSerial.instance;
	}
	ScannerSerial.instance = this;
    }

    async connect(readHandler) {
	this.device = await navigator.serial.requestPort({ filters: [{ usbVendorId: 0x03f0, usbDeviceId: 0x0339 }] })
	await this.device.open({baudRate: 9600})

	console.log(this.device)
	this.reader = this.device.readable.getReader()
	readHandler(this.reader)
    }

    async disconnect() {
	await this.reader.cancel()
	this.reader = null
	this.device.close()
    }

    isConnected() {
	if (this.reader == null) {
	    return false;
	}
	return true
    }
}
