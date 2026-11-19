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

import {Plugin, ButtonView} from 'ckeditor5';
import {BARCODE_IMAGE_SRC_PREFIX, calculateGeometry, getFormField, getParameters} from "./labelGeometry";

//A neutral placeholder box, which can be stretched to the aspect ratio of both 1D and 2D barcodes
const PLACEHOLDER_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64" preserveAspectRatio="none">'
    + '<rect x="1" y="1" width="62" height="62" fill="#e0e0e0" stroke="#000" stroke-width="2" vector-effect="non-scaling-stroke"/>'
    + '<path d="M1 1 L63 63 M63 1 L1 63" stroke="#000" stroke-width="1" vector-effect="non-scaling-stroke"/>'
    + '</svg>';

const BARCODE_IMAGE_SRC = BARCODE_IMAGE_SRC_PREFIX + ';base64,' + btoa(PLACEHOLDER_SVG);

/**
 * Adds a "Barcode" button to the label editor, which inserts a placeholder image for the barcode of the label.
 * It can be positioned like any other image, but its size is defined by the barcode size option of the label
 * (the placeholder is shown in that size by PartDBLabelPreview).
 */
export default class PartDBLabelBarcode extends Plugin {
    static get pluginName() {
        return 'PartDBLabelBarcode';
    }

    init() {
        const editor = this.editor;
        const t = editor.t;

        //Without a barcode type, the placeholder would not be rendered on the label, so it can not be inserted then
        this.set('hasBarcodeType', true);
        const typeField = getFormField(editor, 'barcode_type');
        if (typeField) {
            const update = () => this.set('hasBarcodeType', typeField.value !== 'none');
            update();
            typeField.addEventListener('change', update);
        }

        editor.ui.componentFactory.add('partdb_barcode', locale => {
            const button = new ButtonView(locale);

            button.set({
                label: t('Barcode'),
                withText: true,
            });

            button.bind('tooltip').to(this, 'hasBarcodeType', hasBarcodeType => hasBarcodeType
                ? t('Insert the barcode of the label. Its size is set by the barcode size option.')
                : t('Select a barcode type in the label options to insert the barcode.'));

            const command = editor.commands.get('insertImage');
            button.bind('isEnabled').to(command, 'isEnabled', this, 'hasBarcodeType',
                (commandEnabled, hasBarcodeType) => commandEnabled && hasBarcodeType);

            this.listenTo(button, 'execute', () => {
                this._insertBarcode();
                editor.editing.view.focus();
            });

            return button;
        });
    }

    /**
     * Inserts the barcode placeholder at the same place as the default label layout does:
     * 2D barcodes at the top left with the text wrapped around them, 1D barcodes left aligned below the text.
     * It can be moved and restyled via the image toolbar afterward.
     */
    _insertBarcode() {
        const editor = this.editor;
        const model = editor.model;
        const params = getParameters(editor);
        const is1D = params ? calculateGeometry(editor, params).barcodeType === '1D' : false;

        //The commands must be executed one after another (and not inside one model.change() block), as CKEditor only
        //refreshes their enabled state (which depends on the selection) after a change block is finished.
        model.change(writer => {
            const root = model.document.getRoot();
            const block = is1D ? root.getChild(root.childCount - 1) : root.getChild(0);
            //Put the selection at the start of the first / end of the last block, so the image is inserted before / after it
            writer.setSelection(writer.createPositionAt(block, is1D ? 'end' : 0));
        });

        editor.execute('insertImage', {source: {src: BARCODE_IMAGE_SRC, alt: 'Barcode'}, imageType: 'imageBlock'});
        editor.execute('imageStyle', {value: is1D ? 'alignBlockLeft' : 'alignLeft'});
    }
}
