import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        confirmMsg: String,
        successMsg: String,
        errorMsg: String
    }

    static targets = ["count"]

    async cleanup(event) {
        event.preventDefault();

        if (!confirm(this.confirmMsgValue)) {
            return;
        }

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const data = await response.json();
                alert(this.successMsgValue.replace('%count%', data.count));
                // Update the count displayed in the UI
                if (this.hasCountTarget) {
                    this.countTarget.innerText = '0';
                }
                // Reload page to reflect changes if needed, or just let the user see 0
                window.location.reload();
            } else {
                alert(this.errorMsgValue);
            }
        } catch (error) {
            console.error('Cleanup failed:', error);
            alert(this.errorMsgValue);
        }
    }
}
