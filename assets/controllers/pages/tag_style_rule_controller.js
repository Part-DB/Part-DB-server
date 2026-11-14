/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

import {Controller} from '@hotwired/stimulus';

import '../../css/components/tag_style_rules.css';

/**
 * Keeps the preview of a tag style rule in sync with the fields while they are being edited.
 *
 * The rules only take effect once the settings are saved, so without this the page gives no feedback at all
 * until then: the preview badge showed a fixed word in the colour that was stored, not the tag and colour the
 * user is currently typing. It also spells out in words what the selected match type does, which is far easier
 * to grasp than the words "exact" and "prefix" on their own.
 */
export default class extends Controller {
    static targets = ['matchType', 'pattern', 'color', 'badge'];

    static values = {
        /** Shown in the badge as long as no tag has been entered */
        placeholder: String,
        /** Sentence for an exact rule, with %pattern% as the placeholder for the entered tag */
        exactHint: String,
        /** Sentence for a prefix rule, with %pattern% as the placeholder for the entered prefix */
        prefixHint: String,
    };

    connect() {
        this.update();
    }

    update() {
        const pattern = this.hasPatternTarget ? this.patternTarget.value.trim() : '';
        const isPrefix = this.hasMatchTypeTarget && this.matchTypeTarget.value === 'prefix';
        const color = this.hasColorTarget ? this.colorTarget.value : '';

        this.updateBadge(pattern, isPrefix, color);
    }

    updateBadge(pattern, isPrefix, color) {
        if (!this.hasBadgeTarget) {
            return;
        }

        //A prefix rule matches more than what is typed, so the badge says so with an ellipsis
        let text = pattern === '' ? this.placeholderValue : pattern;
        if (pattern !== '' && isPrefix) {
            text += '…';
        }
        this.badgeTarget.textContent = text;

        //The sentence explaining what this rule matches lives on the badge rather than in a line of its own:
        //a line below the fields changes the height of its row, which breaks the alignment of a dense list and
        //makes it jump while editing. It names the tag in full, which also covers the truncation above.
        this.badgeTarget.title = pattern === ''
            ? ''
            : (isPrefix ? this.prefixHintValue : this.exactHintValue).replace('%pattern%', pattern);

        //"default" means the rule does not force a colour, which is how an unmatched tag looks
        const badgeClass = color === '' || color === 'default' ? 'bg-secondary' : `text-bg-${color}`;
        //The layout classes have to be kept: assigning only badge + colour would drop the truncation again
        this.badgeTarget.className = `badge text-truncate align-middle mw-100 ${badgeClass}`;
    }
}
