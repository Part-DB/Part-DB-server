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

/**
 * Helpers to calculate the geometry of a label in the label editor, based on the label options form.
 * The parameters are defined in LabelHTMLGenerator::getLabelEditorParameters() and passed via a data attribute of the
 * lines field, so that the editor matches the generated label.
 */

/** CSS px per mm (the editor uses px font sizes like the label, so 1mm on the label is this many px in the editor) */
export const PX_PER_MM = 96 / 25.4;

/**
 * Returns the selected image model element, or null.
 */
export function getSelectedImage(editor) {
    return editor.plugins.get('ImageUtils').getClosestSelectedImageElement(editor.model.document.selection);
}

/**
 * Returns the parameters from LabelHTMLGenerator::getLabelEditorParameters(), or null if they are not available.
 */
export function getParameters(editor) {
    const json = editor.sourceElement?.dataset.labelEditorParameters;
    return json ? JSON.parse(json) : null;
}

/**
 * Returns the field of the label options form with the given name (e.g. "width"), or null if it does not exist.
 */
export function getFormField(editor, name) {
    return editor.sourceElement?.form?.querySelector(`[name$="[${name}]"]`) ?? null;
}

/**
 * Calculates the geometry of the label (all values in mm), the same way as the label templates do:
 *  - width/height: The label size, marginX/marginY: The page margins
 *  - barcodeType: "none", "1D" or "2D"
 *  - reserved: The area used by the barcode ({left, top, width, height}, relative to the label), or null if there is no
 *    barcode
 *  - text: The area of the text lines ({left, width}, relative to the label)
 */
export function calculateGeometry(editor, params) {
    const field = (name) => getFormField(editor, name);

    const width = parseFloat(field('width')?.value) || 50;
    const height = parseFloat(field('height')?.value) || 30;
    const type = field('barcode_type')?.value ?? 'none';

    const marginX = params.pageMarginX;
    const marginY = params.pageMarginY;
    const contentWidth = Math.max(width - 2 * marginX, 1);

    const barcodeType = type === 'none' ? 'none' : (params.types1D.includes(type) ? '1D' : '2D');

    let reserved = null;
    let text = {left: marginX, width: contentWidth};

    if (barcodeType === '2D') {
        //Legacy layout of label_page_qr.html.twig
        const layout = params.legacy2D;
        const edge = contentWidth * layout.barcodeWidth;
        reserved = {left: marginX + contentWidth * layout.barcodeLeft, top: marginY, width: edge, height: edge};
        text = {left: marginX + contentWidth * layout.textLeft, width: contentWidth * layout.textWidth};
    } else if (barcodeType === '1D') {
        //Legacy layout of label_page_1d.html.twig
        const layout = params.legacy1D;
        const blockHeight = layout.height + layout.captionHeight;
        reserved = {left: marginX, top: height - marginY - layout.bottom - blockHeight, width: Math.min(layout.maxWidth, contentWidth), height: blockHeight};
    }

    return {width, height, marginX, marginY, barcodeType, reserved, text};
}
