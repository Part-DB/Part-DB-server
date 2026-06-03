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
 * Stimulus controller for backup restore confirmation dialogs.
 * Shows a confirmation dialog with backup details before allowing restore.
 */
export default class extends Controller {
    static values = {
        filename: { type: String, default: '' },
        date: { type: String, default: '' },
        confirmTitle: { type: String, default: 'Restore Backup' },
        confirmMessage: { type: String, default: 'Are you sure you want to restore from this backup?' },
        confirmWarning: { type: String, default: 'This will overwrite your current database. This action cannot be undone!' },
    };

    connect() {
        this.element.addEventListener('submit', this.handleSubmit.bind(this));
    }

    handleSubmit(event) {
        // Always prevent default first
        event.preventDefault();

        // Build confirmation message
        const message = this.confirmTitleValue + '\n\n' +
            'Backup: ' + this.filenameValue + '\n' +
            'Date: ' + this.dateValue + '\n\n' +
            this.confirmMessageValue + '\n\n' +
            '⚠️ ' + this.confirmWarningValue;

        // Only submit if user confirms
        if (confirm(message)) {
            this.element.submit();
        }
    }
}
