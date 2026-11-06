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

/** The gap between wrapped images and the text in mm (the same as --partdb-float-gap in ckeditor.css) */
const FLOAT_GAP = 4;

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

            this._updateWrappedImages(geometry.text.width * PX_PER_MM);
        };

        update();
        form.addEventListener('input', update);
        form.addEventListener('change', update);
        //The margins of wrapped images depend on the images next to them (before other plugins use their position)
        this.listenTo(editor.editing.view, 'render', update, {priority: 'high'});
        //Images without a size get their width when they are loaded (load events do not bubble)
        editor.ui.getEditableElement()?.addEventListener('load', update, true);
    }

    /**
     * Sets the left margin of the images wrapped on the right, depending on the other wrapped images of their row, the
     * same way as LabelHTMLGenerator::fitFloatingImages() does for the label:
     *  - The gap between two images wrapped on the right is the right margin of the left one, so the right one gets no
     *    left margin
     *  - The gap between the images wrapped on the left and the ones wrapped on the right is only needed once: If they do
     *    not fit into a row otherwise, the left margin of the innermost image wrapped on the right is reduced (up to
     *    the gap), so that it overlaps with the right margin of the image wrapped on the left
     * All other margins are defined in ckeditor.css.
     * @param {number} available The width (in px) available for the content of the label
     */
    _updateWrappedImages(available) {
        const editable = this.editor.ui.getEditableElement();
        if (!editable) {
            return;
        }

        const gap = FLOAT_GAP * PX_PER_MM;
        //The elements whose margin was set here, to reset it if they are not wrapped on the right anymore
        this._adjustedImages ??= new WeakSet();
        const setMargin = (element, value) => {
            if (value !== '') {
                this._adjustedImages.add(element);
            } else if (!this._adjustedImages.delete(element)) {
                return;
            }
            if (element.style.marginLeft !== value) {
                element.style.marginLeft = value;
            }
        };

        //The wrapped images of the current row, the width they use and the width by which the gaps overlap
        let row = [];
        let used = 0;
        let overlap = 0;

        const finishRow = () => {
            const rights = row.filter(image => image.isRight);
            rights.forEach((image, index) => {
                if (index < rights.length - 1) {
                    setMargin(image.element, '0px');
                } else {
                    //The innermost image has the gap to the text (browsers round sizes, so stay a little below the limit)
                    setMargin(image.element, overlap > 0 ? Math.max(gap - overlap - 0.5, 0).toFixed(2) + 'px' : '');
                }
            });
            row = [];
            used = 0;
            overlap = 0;
        };

        for (const element of editable.children) {
            const isLeft = element.classList.contains('image-style-align-left');
            const isRight = element.classList.contains('image-style-align-right');
            if (!isRight) {
                setMargin(element, '');
            }
            //Only consecutive wrapped images are placed next to each other
            if (!isLeft && !isRight) {
                finishRow();
                continue;
            }

            const width = Math.min(element.getBoundingClientRect().width, available);
            const hasLeft = row.some(image => !image.isRight);
            const hasRight = row.some(image => image.isRight);
            //Images wrapped on the left have the gap on their right side, images wrapped on the right on their left side,
            //and the outermost one also on its right side (as the content area is wider than the label by the gap)
            let needed = width + (isLeft || hasRight ? gap : 2 * gap);
            let missing = used + needed - (available + gap);
            const maxOverlap = (isLeft ? hasRight : hasLeft) ? gap - overlap : 0;

            if (row.length > 0 && missing > maxOverlap + 0.5) {
                finishRow();
                needed = width + (isLeft ? gap : 2 * gap);
                missing = 0;
            }

            if (row.length > 0 && missing > 0) {
                const shrink = Math.min(missing, gap - overlap);
                overlap += shrink;
                needed -= shrink;
            }

            row.push({element, isRight});
            used += needed;
        }
        finishRow();
    }
}
