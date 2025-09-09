import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["tbody", "addButton", "submitButton"]
    static values = { 
        mappingIndex: Number,
        maxMappings: Number,
        prototype: String,
        maxMappingsReachedMessage: String
    }

    connect() {
        this.updateAddButtonState()
        this.updateFieldOptions()
        this.attachEventListeners()
    }

    attachEventListeners() {
        // Add event listeners to existing field selects
        const fieldSelects = this.tbodyTarget.querySelectorAll('select[name*="[field]"]')
        fieldSelects.forEach(select => {
            select.addEventListener('change', this.updateFieldOptions.bind(this))
        })

        // Add click listener to add button
        if (this.hasAddButtonTarget) {
            this.addButtonTarget.addEventListener('click', this.addMapping.bind(this))
        }

        // Form submit handler
        const form = this.element.querySelector('form')
        if (form && this.hasSubmitButtonTarget) {
            form.addEventListener('submit', this.handleFormSubmit.bind(this))
        }
    }

    addMapping() {
        const currentMappings = this.tbodyTarget.querySelectorAll('.mapping-row').length
        
        if (currentMappings >= this.maxMappingsValue) {
            alert(this.maxMappingsReachedMessageValue)
            return
        }
        
        const newRowHtml = this.prototypeValue.replace(/__name__/g, this.mappingIndexValue)
        const tempDiv = document.createElement('div')
        tempDiv.innerHTML = newRowHtml
        
        const fieldWidget = tempDiv.querySelector('select[name*="[field]"]') || tempDiv.children[0]
        const providerWidget = tempDiv.querySelector('select[name*="[providers]"]') || tempDiv.children[1]
        const priorityWidget = tempDiv.querySelector('input[name*="[priority]"]') || tempDiv.children[2]
        
        const newRow = document.createElement('tr')
        newRow.className = 'mapping-row'
        newRow.innerHTML = `
            <td>${fieldWidget ? fieldWidget.outerHTML : ''}</td>
            <td>${providerWidget ? providerWidget.outerHTML : ''}</td>
            <td>${priorityWidget ? priorityWidget.outerHTML : ''}</td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" data-action="click->field-mapping#removeMapping">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `
        
        this.tbodyTarget.appendChild(newRow)
        this.mappingIndexValue++
        
        const newFieldSelect = newRow.querySelector('select[name*="[field]"]')
        if (newFieldSelect) {
            newFieldSelect.value = ''
            newFieldSelect.addEventListener('change', this.updateFieldOptions.bind(this))
        }
        
        this.updateFieldOptions()
        this.updateAddButtonState()
    }

    removeMapping(event) {
        const row = event.target.closest('tr')
        row.remove()
        this.updateFieldOptions()
        this.updateAddButtonState()
    }

    updateFieldOptions() {
        const fieldSelects = this.tbodyTarget.querySelectorAll('select[name*="[field]"]')
        
        const selectedFields = Array.from(fieldSelects)
            .map(select => select.value)
            .filter(value => value && value !== '')
        
        fieldSelects.forEach(select => {
            Array.from(select.options).forEach(option => {
                const isCurrentValue = option.value === select.value
                const isEmptyOption = !option.value || option.value === ''
                const isAlreadySelected = selectedFields.includes(option.value)
                
                if (!isEmptyOption && isAlreadySelected && !isCurrentValue) {
                    option.disabled = true
                    option.style.display = 'none'
                } else {
                    option.disabled = false
                    option.style.display = ''
                }
            })
        })
    }

    updateAddButtonState() {
        const currentMappings = this.tbodyTarget.querySelectorAll('.mapping-row').length
        
        if (this.hasAddButtonTarget) {
            if (currentMappings >= this.maxMappingsValue) {
                this.addButtonTarget.disabled = true
                this.addButtonTarget.title = this.maxMappingsReachedMessageValue
            } else {
                this.addButtonTarget.disabled = false
                this.addButtonTarget.title = ''
            }
        }
    }

    handleFormSubmit(event) {
        if (this.hasSubmitButtonTarget) {
            this.submitButtonTarget.disabled = true
            
            // Disable the entire form to prevent changes during processing
            const form = event.target
            const formElements = form.querySelectorAll('input, select, textarea, button')
            formElements.forEach(element => {
                if (element !== this.submitButtonTarget) {
                    element.disabled = true
                }
            })
        }
    }
}