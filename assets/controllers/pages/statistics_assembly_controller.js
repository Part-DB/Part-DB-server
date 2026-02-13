import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        cleanupBomUrl: String,
        cleanupPreviewUrl: String
    }

    static targets = ["bomCount", "previewCount", "bomButton", "previewButton"]

    async cleanup(event) {
        if (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }

        const button = event ? event.currentTarget : null;
        if (button) button.disabled = true;

        try {
            const data = await this.fetchWithErrorHandling(this.cleanupBomUrlValue, { method: 'POST' });

            if (data.success) {
                this.showSuccessMessage(data.message);
                if (this.hasBomCountTarget) {
                    this.bomCountTarget.textContent = data.new_count;
                }
                if (data.new_count === 0 && this.hasBomButtonTarget) {
                    this.bomButtonTarget.remove();
                }
            } else {
                this.showErrorMessage(data.message || 'BOM cleanup failed');
            }
        } catch (error) {
            this.showErrorMessage(error.message || 'An unexpected error occurred during BOM cleanup');
        } finally {
            if (button) button.disabled = false;
        }
    }

    async cleanupPreview(event) {
        if (event) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }

        const button = event ? event.currentTarget : null;
        if (button) button.disabled = true;

        try {
            const data = await this.fetchWithErrorHandling(this.cleanupPreviewUrlValue, { method: 'POST' });

            if (data.success) {
                this.showSuccessMessage(data.message);
                if (this.hasPreviewCountTarget) {
                    this.previewCountTarget.textContent = data.new_count;
                }
                if (data.new_count === 0 && this.hasPreviewButtonTarget) {
                    this.previewButtonTarget.remove();
                }
            } else {
                this.showErrorMessage(data.message || 'Preview cleanup failed');
            }
        } catch (error) {
            this.showErrorMessage(error.message || 'An unexpected error occurred during Preview cleanup');
        } finally {
            if (button) button.disabled = false;
        }
    }

    getHeaders() {
        return {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        }
    }

    async fetchWithErrorHandling(url, options = {}, timeout = 30000) {
        const controller = new AbortController()
        const timeoutId = setTimeout(() => controller.abort(), timeout)

        try {
            const response = await fetch(url, {
                ...options,
                headers: { ...this.getHeaders(), ...options.headers },
                signal: controller.signal
            })

            clearTimeout(timeoutId)

            if (!response.ok) {
                const errorText = await response.text()
                let errorMessage = `Server error (${response.status})`;
                try {
                    const errorJson = JSON.parse(errorText);
                    if (errorJson && errorJson.message) {
                        errorMessage = errorJson.message;
                    }
                } catch (e) {
                    // Not a JSON response, use status text
                    errorMessage = `${errorMessage}: ${errorText}`;
                }
                throw new Error(errorMessage)
            }

            return await response.json()
        } catch (error) {
            clearTimeout(timeoutId)

            if (error.name === 'AbortError') {
                throw new Error('Request timed out. Please try again.')
            } else if (error.message.includes('Failed to fetch')) {
                throw new Error('Network error. Please check your connection and try again.')
            } else {
                throw error
            }
        }
    }

    showSuccessMessage(message) {
        this.showToast('success', message)
    }

    showErrorMessage(message) {
        this.showToast('error', message)
    }

    showToast(type, message) {
        // Create a simple alert that doesn't disrupt layout
        const alertId = 'alert-' + Date.now();
        const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';

        const alertHTML = `
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed"
                 style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;"
                 id="${alertId}" role="alert">
                <i class="fas ${iconClass} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        // Add alert to body
        document.body.insertAdjacentHTML('beforeend', alertHTML);

        // Auto-remove after 5 seconds if not closed manually
        setTimeout(() => {
            const elementToRemove = document.getElementById(alertId);
            if (elementToRemove) {
                elementToRemove.remove();
            }
        }, 5000);
    }
}
