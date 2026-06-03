/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

import { Controller } from "@hotwired/stimulus"

/**
 * This controller listens for a special non-printable character (SOH / ASCII 1) to be entered anywhere on the page,
 * which is then used as a trigger to submit the following characters as a barcode / scan input.
 */
export default class extends Controller {
    connect() {
        // Optional: Log to confirm global attachment
        console.log("Scanner listener active")

        this.isCapturing = false
        this.buffer = ""

        window.addEventListener("keypress", this.handleKeydown.bind(this))
    }

    initialize() {
        this.isCapturing = false
        this.buffer = ""
        this.timeoutId = null
    }

    handleKeydown(event) {

        // Ignore if the user is typing in a form field
        const isInput = ["INPUT", "TEXTAREA", "SELECT"].includes(event.target.tagName) ||
            event.target.isContentEditable;
        if (isInput) return

        // 1. Detect Start of Header (SOH / Ctrl+A)
        if (event.key === "\x01" || event.keyCode === 1) {
            this.startCapturing(event)
            return
        }

        // 2. Process characters if in capture mode
        if (this.isCapturing) {
            this.resetTimeout() // Push the expiration back with every keypress

            if (event.key === "Enter" || event.keyCode === 13) {

                this.finishCapturing(event)
            } else if (event.key.length === 1) {
                this.buffer += event.key
            }
        }
    }

    startCapturing(event) {
        this.isCapturing = true
        this.buffer = ""
        this.resetTimeout()
        event.preventDefault()
        console.debug("Scan character detected. Capture started...")
    }

    finishCapturing(event) {
        event.preventDefault()
        const data = this.buffer;
        this.stopCapturing()
        this.processCapture(data)
    }

    stopCapturing() {
        this.isCapturing = false
        this.buffer = ""
        if (this.timeoutId) clearTimeout(this.timeoutId)
        console.debug("Capture cleared/finished.")
    }

    resetTimeout() {
        if (this.timeoutId) clearTimeout(this.timeoutId)

        this.timeoutId = setTimeout(() => {
            if (this.isCapturing) {
                console.warn("Capture timed out. Resetting buffer.")
                this.stopCapturing()
            }
        }, 500)
    }

    processCapture(data) {
        if (!data) return

        console.debug("Captured scan data: " + data)

        const scanInput = document.getElementById("scan_dialog_input");
        if (scanInput) { //When we are on the scan dialog page, submit the form there
            this._submitScanForm(data);
        } else {  //Otherwise use our own form (e.g. on the part list page)
            this.element.querySelector("input[name='input']").value = data;
            this.element.requestSubmit();
        }


    }

    _submitScanForm(data) {
        const scanInput = document.getElementById("scan_dialog_input");
        if (!scanInput) {
            console.error("Scan input field not found!")
            return;
        }

        scanInput.value = data;
        scanInput.dispatchEvent(new Event('input', { bubbles: true }));

        const form = document.getElementById("scan_dialog_form");
        if (!form) {
            console.error("Scan form not found!")
            return;
        }

        form.requestSubmit();
    }
}
