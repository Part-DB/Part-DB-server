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

import {Plugin} from 'ckeditor5';
import {calculateGeometry, getParameters, PX_PER_MM} from "./labelGeometry";

/**
 * Shows the editable area of the label editor in the real size of the label (at the same scale as the text), with the
 * page margins and the area reserved for the barcode, so that e.g. right aligned content is shown
 * like on the label. The editor itself keeps its width. The styles are defined in ckeditor.css (.partdb-label-preview).
 */
export default class PartDBLabelPreview extends Plugin {
    static get pluginName() {
        return 'PartDBLabelPreview';
    }

    init() {
        this.editor.on('ready', () => this._init());
    }

    _init() {
        const editor = this.editor;
        const form = editor.sourceElement?.form;
        const root = editor.ui.view.element;
        const params = getParameters(editor);
        if (!form || !root || !params) {
            return;
        }

        const px = (mm) => (mm * PX_PER_MM) + 'px';

        const update = () => {
            const geometry = calculateGeometry(editor, params);
            const style = root.style;

            style.setProperty('--partdb-label-width', px(geometry.width));
            style.setProperty('--partdb-label-height', px(geometry.height));
            style.setProperty('--partdb-label-padding-top', px(geometry.marginY));
            style.setProperty('--partdb-label-padding-bottom', px(geometry.marginY));
            style.setProperty('--partdb-label-padding-left', px(geometry.text.left));
            style.setProperty('--partdb-label-padding-right', px(geometry.width - geometry.text.left - geometry.text.width));

            //The area used by the barcode
            root.classList.toggle('partdb-label-has-reserved', geometry.reserved !== null);
            if (geometry.reserved) {
                style.setProperty('--partdb-reserved-left', px(geometry.reserved.left));
                style.setProperty('--partdb-reserved-top', px(geometry.reserved.top));
                style.setProperty('--partdb-reserved-width', px(geometry.reserved.width));
                style.setProperty('--partdb-reserved-height', px(geometry.reserved.height));
            }

            root.classList.add('partdb-label-preview');
        };

        update();
        form.addEventListener('input', update);
        form.addEventListener('change', update);
    }
}
