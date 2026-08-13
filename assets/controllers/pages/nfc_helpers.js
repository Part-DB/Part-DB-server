export function isWebNfcAvailable() {
    return window.isSecureContext && "NDEFReader" in window;
}

export function decodeNdefMessage(message) {
    for (const record of message.records) {
        if (!["url", "absolute-url", "text"].includes(record.recordType) || !record.data) continue;

        try {
            const value = new TextDecoder(record.encoding || "utf-8").decode(record.data).trim();
            if (value) return value;
        } catch (_) {
            // Ignore records with unsupported encodings and try the next record.
        }
    }

    return null;
}

export function setScanInputAndSubmit(value) {
    const input = document.getElementById("scan_dialog_input");
    const form = document.getElementById("scan_dialog_form");
    if (!input || !form) return false;

    input.value = value;
    input.dispatchEvent(new Event("input", {bubbles: true}));
    form.requestSubmit();
    return true;
}
