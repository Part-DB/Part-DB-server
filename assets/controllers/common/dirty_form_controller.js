/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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

import {Controller} from "@hotwired/stimulus";
import {visit} from "@hotwired/turbo";
import {ConfirmSwal} from "../../helpers/swal";
import "../../css/components/dirty_form.css";

/**
 * Attach to a <form> element (or a wrapper containing a <form>) to prevent accidental navigation
 * away when the form has unsaved changes.
 *
 * Dirty detection is event-driven: `change` and `input` events bubble up to the form and trigger
 * a check of whether any element's current value differs from the DOM default recorded in the HTML
 * (`defaultValue` / `defaultChecked` / `option.defaultSelected`).  Using both events covers both
 * native widgets (which fire `change`) and rich-text editors like CKEditor (which fire `input`
 * when they sync their underlying textarea).
 *
 * Validation failures (server returns 200 with `.is-invalid` fields) are always treated as dirty:
 * the submitted data was never saved, so navigating away would lose it.  This removes the need for
 * any snapshot mechanism — the `.is-invalid` classes in the re-rendered HTML are the signal.
 *
 * Intercepts three navigation paths:
 *  1. Any <a href> link click (capture phase)
 *  2. window beforeunload
 *  3. turbo:before-visit
 *
 * Values:
 *  - confirmTitle   (String) – dialog title
 *  - confirmMessage (String) – dialog body text
 */
export default class extends Controller {
    static values = {
        confirmTitle: {type: String, default: 'Unsaved Changes'},
        confirmMessage: {type: String, default: 'You have unsaved changes. Are you sure you want to leave this page?'},
    };

    connect() {
        this._form = (this.element.tagName === 'FORM') ? this.element : this.element.querySelector('form');
        this._isDirty = false;
        this._submitting = false;
        this._navigating = false;

        this._changeHandler = this._handleChange.bind(this);
        this._linkClickHandler = this._handleLinkClick.bind(this);
        this._beforeUnloadHandler = this._handleBeforeUnload.bind(this);
        this._turboBeforeVisitHandler = this._handleTurboBeforeVisit.bind(this);
        this._turboSubmitEndHandler = this._handleTurboSubmitEnd.bind(this);

        if (this._form) {
            this._form.addEventListener('change', this._changeHandler);
            // CKEditor (and other rich-text widgets) dispatch `input` rather than `change`
            // when their underlying textarea value is updated.
            this._form.addEventListener('input', this._changeHandler);
        }
        document.addEventListener('click', this._linkClickHandler, true);
        window.addEventListener('beforeunload', this._beforeUnloadHandler);
        document.addEventListener('turbo:before-visit', this._turboBeforeVisitHandler);
        document.addEventListener('turbo:submit-end', this._turboSubmitEndHandler);

        const modal = this.element.closest('.modal');
        if (modal) {
            this._modal = modal;
            this._modalHideHandler = this._handleModalHide.bind(this);
            modal.addEventListener('hide.bs.modal', this._modalHideHandler);
        }
    }

    disconnect() {
        if (this._form) {
            this._form.removeEventListener('change', this._changeHandler);
            this._form.removeEventListener('input', this._changeHandler);
        }
        document.removeEventListener('click', this._linkClickHandler, true);
        window.removeEventListener('beforeunload', this._beforeUnloadHandler);
        document.removeEventListener('turbo:before-visit', this._turboBeforeVisitHandler);
        document.removeEventListener('turbo:submit-end', this._turboSubmitEndHandler);

        if (this._modal && this._modalHideHandler) {
            this._modal.removeEventListener('hide.bs.modal', this._modalHideHandler);
        }
    }

    /** data-action="submit->common--dirty-form#submit" — suppresses the guard while saving. */
    submit() {
        this._submitting = true;
    }

    /**
     * data-action="reset->common--dirty-form#resetDirtyState" — marks the form as clean after
     * a programmatic reset. Native change events are not fired by form.reset(), so we set the
     * flag directly.  Turbo also calls form.reset() internally before the post-submit redirect;
     * the _submitting guard prevents that from incorrectly clearing the flag.
     */
    resetDirtyState() {
        if (this._submitting) return;

        // Wait for a frame to allow the form's DOM state to update after the reset() call, then refresh markers and update the dirty flag.
        requestAnimationFrame(() => {
            this._isDirty = false;
            this._clearDirtyMarkers();
        });
    }

    _handleChange(event) {
        const target = event?.target;
        if (target?.name) {
            this._updateDirtyMarker(target);
        } else {
            this._refreshDirtyMarkers();
        }
        this._isDirty = this._form?.querySelector('[data-dirty]') !== null;
    }

    /**
     * Walk every named form element and update its `data-dirty` attribute.
     * Un-named elements (e.g. the visible TristateCheckbox whose name was removed) are
     * skipped — they are not submitted and are not the source of truth for form data.
     */
    _refreshDirtyMarkers() {
        if (!this._form) return;
        for (const el of this._form.elements) {
            if (!el.name) continue;
            this._updateDirtyMarker(el);
        }
    }

    /**
     * Set or clear `data-dirty` on a single named form element.
     * Hidden inputs are not visually rendered, so special handling applies:
     *  - TristateCheckbox: the hidden backing input is preceded by a nameless visual checkbox —
     *    mark that instead.
     *  - Other hidden inputs (e.g. CSRF tokens): ignored.
     * TomSelect hides the <select> before .ts-wrapper (sibling); CSS targets .ts-control via the
     * adjacent-sibling combinator on the select's data-dirty attribute.
     */
    _updateDirtyMarker(el) {
        if (el.type === 'hidden') {
            const visual = el.previousElementSibling;
            if (visual instanceof HTMLInputElement && !visual.name) {
                visual.toggleAttribute('data-dirty', el.value !== el.defaultValue);
            }
            return;
        }

        const dirty = this._isElementDirty(el);
        el.toggleAttribute('data-dirty', dirty);
    }

    _clearDirtyMarkers() {
        this._form?.querySelectorAll('[data-dirty]').forEach(el => el.removeAttribute('data-dirty'));
    }

    _isElementDirty(el) {
        //Disabled elements are not editable, so ignore them even if their value differs from the default.
        if (el.disabled) return false;

        if (el.type === 'file') return false;
        if (el.type === 'checkbox' || el.type === 'radio') {
            return el.checked !== el.defaultChecked;
        }
        if (el.tagName === 'SELECT') {
            // TomSelect sets data-default-value to the value at init time.
            // The native option.defaultSelected approach is unreliable when no option
            // carries the `selected` attribute — the browser auto-selects option[0]
            // (selected=true) while defaultSelected stays false, causing a false positive.
            if (el.dataset.defaultValue !== undefined) {
                return el.value !== el.dataset.defaultValue;
            }
            for (const option of el.options) {
                if (option.selected !== option.defaultSelected) return true;
            }
            return false;
        }

        let defaultValue = el.defaultValue;

        //If an element has an data-default-value, use that for dirty checking instead of the DOM default Value. Set for example by the ckeditor-controller
        if (el.dataset.defaultValue !== undefined) {
            defaultValue = el.dataset.defaultValue;
        }
        return el.value !== defaultValue;
    }

    _isFormDirty() {
        if (this._submitting) return false;
        // A form with validation errors was submitted but never saved — always treat as dirty.
        if (this._form?.querySelector('.is-invalid')) return true;
        return this._isDirty;
    }

    _confirmNavigation(onConfirm) {
        ConfirmSwal.fire({
            titleText: this.confirmTitleValue,
            text: this.confirmMessageValue,
        }).then(({isConfirmed}) => { if (isConfirmed) onConfirm(); });
    }

    _handleLinkClick(event) {
        if (this._navigating) return;

        const link = event.target.closest('a[href]');
        if (!link) return;

        const href = link.getAttribute('href');
        if (!href || href.startsWith('#')) return;
        if (link.target === '_blank' || link.target === '_top' || link.target === '_parent') return;
        if (link.hasAttribute('data-dirty-form-ignore')) return;

        if (!this._isFormDirty()) return;

        event.preventDefault();
        event.stopPropagation();
        this._confirmNavigation(() => { this._navigating = true; link.click(); });
    }

    _handleBeforeUnload(event) {
        if (this._navigating || !this._isFormDirty()) return;
        event.preventDefault();
        event.returnValue = '';
    }

    _handleTurboBeforeVisit(event) {
        if (this._navigating || !this._isFormDirty()) return;

        event.preventDefault();
        const url = event.detail.url;
        const frame = event.detail.frame;
        this._confirmNavigation(() => {
            this._navigating = true;
            if (frame) { window.Turbo.visit(url, { frame }); } else { visit(url); }
        });
    }

    _handleTurboSubmitEnd(event) {
        const submittedForm = event.detail?.formSubmission?.formElement;
        if (submittedForm !== this._form) return;

        // For a successful save (redirect), the controller will disconnect with the Turbo
        // navigation; reset is only needed for validation errors where the form stays in the DOM.
        const savedSuccessfully = event.detail.success && event.detail.fetchResponse?.redirected;
        if (!savedSuccessfully) {
            this._submitting = false;
        }
    }

    _handleModalHide(event) {
        if (this._navigating || !this._isFormDirty()) return;

        event.preventDefault();
        this._confirmNavigation(() => {
            this._navigating = true;
            window.bootstrap?.Modal?.getInstance(this._modal)?.hide();
        });
    }
}
