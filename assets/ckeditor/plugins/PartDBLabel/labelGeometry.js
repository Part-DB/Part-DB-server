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

/**
 * The start of the src of the barcode placeholder image. The label generator replaces all images with this src with the
 * barcode of the label (see LabelHTMLGenerator::BARCODE_IMAGE_SRC_PREFIX).
 */
export const BARCODE_IMAGE_SRC_PREFIX = 'data:image/svg+xml;partdb=barcode';

/**
 * The start of the src of the part image placeholder (see LabelHTMLGenerator::PART_IMAGE_SRC_PREFIX)
 */
export const PART_IMAGE_SRC_PREFIX = 'data:image/svg+xml;partdb=part-image';

/** The label target types (values of LabelSupportedElement), which have a part image */
export const PART_IMAGE_ELEMENTS = ['part', 'part_lot'];

/** CSS px per mm (the editor uses px font sizes like the label, so 1mm on the label is this many px in the editor) */
export const PX_PER_MM = 96 / 25.4;

/**
 * Returns the kind of the given image model element: null (no image), "barcode" (barcode placeholder),
 * "part-image" (part image placeholder) or "image" (a normal image).
 */
export function getImageKind(image) {
    if (!image) {
        return null;
    }
    const src = String(image.getAttribute('src') ?? '');
    if (src.startsWith(BARCODE_IMAGE_SRC_PREFIX)) {
        return 'barcode';
    }
    return src.startsWith(PART_IMAGE_SRC_PREFIX) ? 'part-image' : 'image';
}

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
 * Returns true if the editor content contains a barcode placeholder image.
 */
export function containsBarcodePlaceholder(editor) {
    const model = editor.model;
    for (const item of model.createRangeIn(model.document.getRoot()).getItems()) {
        if (item.is('element') && (item.name === 'imageBlock' || item.name === 'imageInline')
            && String(item.getAttribute('src') ?? '').startsWith(BARCODE_IMAGE_SRC_PREFIX)) {
            return true;
        }
    }
    return false;
}

/**
 * Calculates the geometry of the label (all values in mm), the same way as LabelHTMLGenerator::getBarcodeLayout() and
 * the label templates do:
 *  - width/height: The label size, marginX/marginY: The page margins
 *  - barcodeType: "none", "1D" or "2D"
 *  - barcode: The size of the barcode ({width, height})
 *  - reserved: The area used by the default barcode layout ({left, top, width, height}, relative to the label), or null
 *    if the barcode is placed inside the content (or there is no barcode)
 *  - text: The area of the text lines ({left, width}, relative to the label)
 */
export function calculateGeometry(editor, params) {
    const field = (name) => getFormField(editor, name);

    const width = parseFloat(field('width')?.value) || 50;
    const height = parseFloat(field('height')?.value) || 30;
    const size = parseFloat(field('barcode_size')?.value) || null;
    const type = field('barcode_type')?.value ?? 'none';

    const marginX = params.pageMarginX;
    const marginY = params.pageMarginY;
    const contentWidth = Math.max(width - 2 * marginX, 1);
    const contentHeight = Math.max(height - 2 * marginY, 1);

    const barcodeType = type === 'none' ? 'none' : (params.types1D.includes(type) ? '1D' : '2D');

    //Same calculation as in LabelHTMLGenerator::getBarcodeLayout()
    let barcode = null;
    if (barcodeType === '1D') {
        barcode = {width: size ?? Math.min(params.default1DMaxWidth, contentWidth), height: params.default1DHeight};
    } else if (barcodeType === '2D') {
        const edge = size ?? Math.min(contentWidth * params.default2DWidthFactor, contentHeight);
        barcode = {width: edge, height: edge};
    }

    let reserved = null;
    let text = {left: marginX, width: contentWidth};

    if (barcodeType !== 'none' && !containsBarcodePlaceholder(editor)) {
        if (size !== null) {
            //Layout of label_page_barcode.html.twig
            const layout = params.sizedLayout;
            if (barcodeType === '2D') {
                reserved = {left: marginX, top: marginY, width: barcode.width, height: barcode.height};
                text = {left: marginX + barcode.width + layout.sideGap, width: Math.max(contentWidth - barcode.width - layout.sideGap, 1)};
            } else {
                const blockHeight = barcode.height + layout.stackedGap + layout.captionHeight;
                reserved = {left: marginX, top: height - marginY - blockHeight, width: barcode.width, height: blockHeight};
            }
        } else if (barcodeType === '2D') {
            //Legacy layout of label_page_qr.html.twig
            const layout = params.legacy2D;
            const edge = contentWidth * layout.barcodeWidth;
            reserved = {left: marginX + contentWidth * layout.barcodeLeft, top: marginY, width: edge, height: edge};
            text = {left: marginX + contentWidth * layout.textLeft, width: contentWidth * layout.textWidth};
        } else {
            //Legacy layout of label_page_1d.html.twig
            const layout = params.legacy1D;
            const blockHeight = layout.height + layout.captionHeight;
            reserved = {left: marginX, top: height - marginY - layout.bottom - blockHeight, width: Math.min(layout.maxWidth, contentWidth), height: blockHeight};
        }
    }

    return {width, height, marginX, marginY, barcodeType, barcode, reserved, text};
}
