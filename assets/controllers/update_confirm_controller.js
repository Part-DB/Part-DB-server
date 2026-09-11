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

import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for update/downgrade confirmation dialogs.
 * Intercepts form submission and shows a confirmation dialog before proceeding.
 */
export default class extends Controller {
    static values = {
        isDowngrade: { type: Boolean, default: false },
        targetVersion: { type: String, default: '' },
        confirmUpdate: { type: String, default: 'Are you sure you want to update Part-DB?' },
        confirmDowngrade: { type: String, default: 'Are you sure you want to downgrade Part-DB?' },
        downgradeWarning: { type: String, default: 'WARNING: This version does not include the Update Manager.' },
        minUpdateManagerVersion: { type: String, default: '2.6.0' },
    };

    connect() {
        this.element.addEventListener('submit', this.handleSubmit.bind(this));
    }

    handleSubmit(event) {
        // Always prevent default first
        event.preventDefault();

        const targetClean = this.targetVersionValue.replace(/^v/, '');
        let message;

        if (this.isDowngradeValue) {
            // Check if downgrading to a version without Update Manager
            if (this.compareVersions(targetClean, this.minUpdateManagerVersionValue) < 0) {
                message = this.confirmDowngradeValue + '\n\n⚠️ ' + this.downgradeWarningValue;
            } else {
                message = this.confirmDowngradeValue;
            }
        } else {
            message = this.confirmUpdateValue;
        }

        // Only submit if user confirms
        if (confirm(message)) {
            // Remove the event listener to prevent infinite loop, then submit
            this.element.removeEventListener('submit', this.handleSubmit.bind(this));
            this.element.submit();
        }
    }

    /**
     * Compare two version strings (e.g., "2.5.0" vs "2.6.0")
     * Returns -1 if v1 < v2, 0 if equal, 1 if v1 > v2
     */
    compareVersions(v1, v2) {
        const parts1 = v1.split('.').map(Number);
        const parts2 = v2.split('.').map(Number);
        for (let i = 0; i < Math.max(parts1.length, parts2.length); i++) {
            const p1 = parts1[i] || 0;
            const p2 = parts2[i] || 0;
            if (p1 < p2) return -1;
            if (p1 > p2) return 1;
        }
        return 0;
    }
}
