import { Controller } from "@hotwired/stimulus"
import { generateCsrfHeaders } from "./csrf_protection_controller"

export default class extends Controller {
    static values = {
        deleteUrl: String,
        stopUrl: String,
        deleteConfirmMessage: String,
        stopConfirmMessage: String
    }

    connect() {
        // Controller initialized
    }
    getHeaders() {
        const headers = {
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
    async deleteJob(event) {
        const jobId = event.currentTarget.dataset.jobId
        const confirmMessage = this.deleteConfirmMessageValue || 'Are you sure you want to delete this job?'
        
        if (confirm(confirmMessage)) {
            try {
                const deleteUrl = this.deleteUrlValue.replace('__JOB_ID__', jobId)
                
                const response = await fetch(deleteUrl, {
                    method: 'DELETE',
                    headers: this.getHeaders()
                })
                
                if (!response.ok) {
                    const errorText = await response.text()
                    throw new Error(`HTTP ${response.status}: ${errorText}`)
                }
                
                const data = await response.json()
                
                if (data.success) {
                    location.reload()
                } else {
                    alert('Error deleting job: ' + (data.error || 'Unknown error'))
                }
            } catch (error) {
                console.error('Error deleting job:', error)
                alert('Error deleting job: ' + error.message)
            }
        }
    }

    async stopJob(event) {
        const jobId = event.currentTarget.dataset.jobId
        const confirmMessage = this.stopConfirmMessageValue || 'Are you sure you want to stop this job?'
        
        if (confirm(confirmMessage)) {
            try {
                const stopUrl = this.stopUrlValue.replace('__JOB_ID__', jobId)
                
                const response = await fetch(stopUrl, {
                    method: 'POST',
                    headers: this.getHeaders()
                })
                
                if (!response.ok) {
                    const errorText = await response.text()
                    throw new Error(`HTTP ${response.status}: ${errorText}`)
                }
                
                const data = await response.json()
                
                if (data.success) {
                    location.reload()
                } else {
                    alert('Error stopping job: ' + (data.error || 'Unknown error'))
                }
            } catch (error) {
                console.error('Error stopping job:', error)
                alert('Error stopping job: ' + error.message)
            }
        }
    }
}