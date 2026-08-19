/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {Controller} from "@hotwired/stimulus";
import {isWebNfcAvailable} from "./nfc_helpers";

/* stimulusFetch: 'lazy' */

export default class extends Controller {
    static targets = ["button", "overwriteButton", "status"];
    static values = {url: String};
    _abortController = null;

    connect() {
        if (isWebNfcAvailable()) this.element.classList.remove("d-none");
    }

    disconnect() {
        this._abortController?.abort();
        this._abortController = null;
    }

    async write(event) {
        await this._write(event.currentTarget.dataset.overwrite === "true");
    }

    async _write(overwrite) {
        if (this._abortController) return;

        this._abortController = new AbortController();
        this.buttonTarget.disabled = true;
        this.overwriteButtonTarget.disabled = true;
        this.overwriteButtonTarget.classList.add("d-none");
        this.statusTarget.className = "small text-muted mt-2";
        this.statusTarget.textContent = this.statusTarget.dataset.waiting;

        try {
            const writer = new NDEFReader();
            await writer.write(
                {records: [{recordType: "url", data: this.urlValue}]},
                {overwrite, signal: this._abortController.signal},
            );
            this.statusTarget.className = "small text-success mt-2";
            this.statusTarget.textContent = this.statusTarget.dataset.success;
        } catch (error) {
            await this._showError(error, overwrite);
        } finally {
            this._abortController = null;
            this.buttonTarget.disabled = false;
        }
    }

    async _showError(error, overwrite) {
        this.statusTarget.className = "small text-danger mt-2";

        if (error.name === "NotAllowedError" && !overwrite) {
            try {
                const permission = await navigator.permissions?.query({name: "nfc"});
                if (permission.state === "denied") {
                    this.statusTarget.textContent = this.statusTarget.dataset.permissionDenied;
                    return;
                }
            } catch (_) {
                // The NFC permission descriptor is not exposed by every supporting browser.
            }

            this.statusTarget.textContent = this.statusTarget.dataset.overwriteConfirmation;
            this.overwriteButtonTarget.classList.remove("d-none");
            this.overwriteButtonTarget.disabled = false;
            return;
        }

        const messageKey = {
            NotAllowedError: "notAllowed",
            NotSupportedError: "unsupportedTag",
            NetworkError: "writeFailed",
            AbortError: "cancelled",
        }[error.name] || "writeFailed";
        this.statusTarget.textContent = this.statusTarget.dataset[messageKey];
    }
}
