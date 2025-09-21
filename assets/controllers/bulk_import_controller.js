import { Controller } from "@hotwired/stimulus"
import { generateCsrfHeaders } from "./csrf_protection_controller"

export default class extends Controller {
    static targets = ["progressBar", "progressText"]
    static values = { 
        jobId: Number,
        partId: Number,
        researchUrl: String,
        researchAllUrl: String,
        markCompletedUrl: String,
        markSkippedUrl: String,
        markPendingUrl: String
    }

    connect() {
        // Auto-refresh progress if job is in progress
        if (this.hasProgressBarTarget) {
            this.startProgressUpdates()
        }
        
        // Restore scroll position after page reload (if any)
        this.restoreScrollPosition()
    }

    getHeaders() {
        const headers = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }

        // Add CSRF headers if available
        const form = document.querySelector('form')
        if (form) {
            const csrfHeaders = generateCsrfHeaders(form)
            Object.assign(headers, csrfHeaders)
        }

        return headers
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
                throw new Error(`Server error (${response.status}): ${errorText}`)
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

    disconnect() {
        if (this.progressInterval) {
            clearInterval(this.progressInterval)
        }
    }

    startProgressUpdates() {
        // Progress updates are handled via page reload for better reliability
        // No need for periodic updates since state changes trigger page refresh
    }

    restoreScrollPosition() {
        const savedPosition = sessionStorage.getItem('bulkImportScrollPosition')
        if (savedPosition) {
            // Restore scroll position after a small delay to ensure page is fully loaded
            setTimeout(() => {
                window.scrollTo(0, parseInt(savedPosition))
                // Clear the saved position so it doesn't interfere with normal navigation
                sessionStorage.removeItem('bulkImportScrollPosition')
            }, 100)
        }
    }

    async markCompleted(event) {
        const partId = event.currentTarget.dataset.partId
        
        try {
            const url = this.markCompletedUrlValue.replace('__PART_ID__', partId)
            const data = await this.fetchWithErrorHandling(url, { method: 'POST' })
            
            if (data.success) {
                this.updateProgressDisplay(data)
                this.markRowAsCompleted(partId)
                
                if (data.job_completed) {
                    this.showJobCompletedMessage()
                }
            } else {
                this.showErrorMessage(data.error || 'Failed to mark part as completed')
            }
        } catch (error) {
            console.error('Error marking part as completed:', error)
            this.showErrorMessage(error.message || 'Failed to mark part as completed')
        }
    }

    async markSkipped(event) {
        const partId = event.currentTarget.dataset.partId
        const reason = prompt('Reason for skipping (optional):') || ''
        
        try {
            const url = this.markSkippedUrlValue.replace('__PART_ID__', partId)
            const data = await this.fetchWithErrorHandling(url, {
                method: 'POST',
                body: JSON.stringify({ reason })
            })
            
            if (data.success) {
                this.updateProgressDisplay(data)
                this.markRowAsSkipped(partId)
            } else {
                this.showErrorMessage(data.error || 'Failed to mark part as skipped')
            }
        } catch (error) {
            console.error('Error marking part as skipped:', error)
            this.showErrorMessage(error.message || 'Failed to mark part as skipped')
        }
    }

    async markPending(event) {
        const partId = event.currentTarget.dataset.partId
        
        try {
            const url = this.markPendingUrlValue.replace('__PART_ID__', partId)
            const data = await this.fetchWithErrorHandling(url, { method: 'POST' })
            
            if (data.success) {
                this.updateProgressDisplay(data)
                this.markRowAsPending(partId)
            } else {
                this.showErrorMessage(data.error || 'Failed to mark part as pending')
            }
        } catch (error) {
            console.error('Error marking part as pending:', error)
            this.showErrorMessage(error.message || 'Failed to mark part as pending')
        }
    }

    updateProgressDisplay(data) {
        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = `${data.progress}%`
            this.progressBarTarget.setAttribute('aria-valuenow', data.progress)
        }
        
        if (this.hasProgressTextTarget) {
            this.progressTextTarget.textContent = `${data.completed_count} / ${data.total_count} completed`
        }
    }

    markRowAsCompleted(partId) {
        // Save scroll position and refresh page to show updated state
        sessionStorage.setItem('bulkImportScrollPosition', window.scrollY.toString())
        window.location.reload()
    }

    markRowAsSkipped(partId) {
        // Save scroll position and refresh page to show updated state
        sessionStorage.setItem('bulkImportScrollPosition', window.scrollY.toString())
        window.location.reload()
    }

    markRowAsPending(partId) {
        // Save scroll position and refresh page to show updated state
        sessionStorage.setItem('bulkImportScrollPosition', window.scrollY.toString())
        window.location.reload()
    }

    showJobCompletedMessage() {
        const alert = document.createElement('div')
        alert.className = 'alert alert-success alert-dismissible fade show'
        alert.innerHTML = `
            <i class="fas fa-check-circle"></i>
            Job completed! All parts have been processed.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `
        
        const container = document.querySelector('.card-body')
        container.insertBefore(alert, container.firstChild)
    }

    async researchPart(event) {
        event.preventDefault()
        event.stopPropagation()
        
        const partId = event.currentTarget.dataset.partId
        const spinner = event.currentTarget.querySelector(`[data-research-spinner="${partId}"]`)
        const button = event.currentTarget
        
        // Show loading state
        if (spinner) {
            spinner.style.display = 'inline-block'
        }
        button.disabled = true
        
        try {
            const url = this.researchUrlValue.replace('__PART_ID__', partId)
            const controller = new AbortController()
            const timeoutId = setTimeout(() => controller.abort(), 30000) // 30 second timeout
            
            const response = await fetch(url, {
                method: 'POST',
                headers: this.getHeaders(),
                signal: controller.signal
            })

            clearTimeout(timeoutId)

            if (!response.ok) {
                const errorText = await response.text()
                throw new Error(`Server error (${response.status}): ${errorText}`)
            }

            const data = await response.json()
            
            if (data.success) {
                this.showSuccessMessage(`Research completed for part. Found ${data.results_count} results.`)
                // Save scroll position and reload to show updated results
                sessionStorage.setItem('bulkImportScrollPosition', window.scrollY.toString())
                window.location.reload()
            } else {
                this.showErrorMessage(data.error || 'Research failed')
            }
        } catch (error) {
            console.error('Error researching part:', error)
            
            if (error.name === 'AbortError') {
                this.showErrorMessage('Research timed out. Please try again.')
            } else if (error.message.includes('Failed to fetch')) {
                this.showErrorMessage('Network error. Please check your connection and try again.')
            } else {
                this.showErrorMessage(error.message || 'Research failed due to an unexpected error')
            }
        } finally {
            // Hide loading state
            if (spinner) {
                spinner.style.display = 'none'
            }
            button.disabled = false
        }
    }

    async researchAllParts(event) {
        event.preventDefault()
        event.stopPropagation()
        
        const spinner = document.getElementById('research-all-spinner')
        const button = event.currentTarget
        
        // Show loading state
        if (spinner) {
            spinner.style.display = 'inline-block'
        }
        button.disabled = true
        
        try {
            const controller = new AbortController()
            const timeoutId = setTimeout(() => controller.abort(), 120000) // 2 minute timeout for bulk operations
            
            const response = await fetch(this.researchAllUrlValue, {
                method: 'POST',
                headers: this.getHeaders(),
                signal: controller.signal
            })

            clearTimeout(timeoutId)

            if (!response.ok) {
                const errorText = await response.text()
                throw new Error(`Server error (${response.status}): ${errorText}`)
            }

            const data = await response.json()
            
            if (data.success) {
                this.showSuccessMessage(`Research completed for ${data.researched_count} parts.`)
                // Save scroll position and reload to show updated results
                sessionStorage.setItem('bulkImportScrollPosition', window.scrollY.toString())
                window.location.reload()
            } else {
                this.showErrorMessage(data.error || 'Bulk research failed')
            }
        } catch (error) {
            console.error('Error researching all parts:', error)
            
            if (error.name === 'AbortError') {
                this.showErrorMessage('Bulk research timed out. This may happen with large batches. Please try again or process smaller batches.')
            } else if (error.message.includes('Failed to fetch')) {
                this.showErrorMessage('Network error. Please check your connection and try again.')
            } else {
                this.showErrorMessage(error.message || 'Bulk research failed due to an unexpected error')
            }
        } finally {
            // Hide loading state
            if (spinner) {
                spinner.style.display = 'none'
            }
            button.disabled = false
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
        const alertId = 'alert-' + Date.now()
        const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger'
        
        const alertHTML = `
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;" 
                 id="${alertId}">
                <i class="fas ${iconClass} me-2"></i>
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()" aria-label="Close"></button>
            </div>
        `
        
        // Add alert to body
        document.body.insertAdjacentHTML('beforeend', alertHTML)
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            const alertElement = document.getElementById(alertId)
            if (alertElement) {
                alertElement.remove()
            }
        }, 5000)
    }
}