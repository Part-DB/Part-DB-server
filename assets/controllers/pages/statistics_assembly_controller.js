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
        const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
        const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
        const title = type === 'success' ? 'Success' : 'Error';
        const timeString = new Date().toLocaleString(undefined, {
            year: '2-digit',
            month: 'numeric',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });

        const toastHTML = `
            <div role="alert" aria-live="assertive" aria-atomic="true" data-delay="5000" data-controller="common--toast" class="toast shadow fade show">
                <div class="toast-header ${bgClass} text-white">
                    <i class="fas fa-fw ${iconClass} me-2"></i>
                    <strong class="me-auto">${title}</strong>
                    <small class="text-white">${timeString}</small>
                    <button type="button" class="ms-2 mb-1 btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body ${bgClass} text-white">
                    ${message}
                </div>
            </div>
        `;

        // Add toast to body. The common--toast controller will move it to the container.
        document.body.insertAdjacentHTML('beforeend', toastHTML);
    }
}
