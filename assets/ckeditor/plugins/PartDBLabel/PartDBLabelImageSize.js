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

import {Plugin, ButtonView, View, LabeledFieldView, createLabeledInputNumber, createDropdown, submitHandler} from 'ckeditor5';
import {getImageKind, getSelectedImage, PX_PER_MM} from "./labelGeometry";
import {DEFAULT_SIZE, setPartImagePlaceholderSize} from "./PartDBLabelPartImage";

/**
 * The form of the "Size" dropdown, with the width and height of the image in mm
 */
class ImageSizeFormView extends View {
    constructor(locale) {
        super(locale);
        const t = locale.t;

        this.widthInput = this._createInput(t('Width (mm)'));
        this.heightInput = this._createInput(t('Height (mm)'));

        this.saveButton = new ButtonView(locale);
        this.saveButton.set({label: t('Save'), withText: true, type: 'submit', class: 'ck-button-action'});

        this.setTemplate({
            tag: 'form',
            attributes: {class: ['ck', 'partdb-image-size-form'], tabindex: '-1'},
            children: [this.widthInput, this.heightInput, this.saveButton],
        });
    }

    render() {
        super.render();
        submitHandler({view: this});
    }

    _createInput(label) {
        const field = new LabeledFieldView(this.locale, createLabeledInputNumber);
        field.label = label;
        field.fieldView.set({min: 0.1, step: 'any'});
        return field;
    }
}

/**
 * Adds a "Size" dropdown to the image toolbar, to set the size of the selected image in mm:
 *  - Part image placeholders get exactly the given size (the part image is fitted into it)
 *  - Other images keep their aspect ratio: The changed dimension is used, and the other one is calculated from it
 *  - For barcode placeholders the dropdown is hidden, as their size is defined by the barcode size option of the label
 */
export default class PartDBLabelImageSize extends Plugin {
    static get pluginName() {
        return 'PartDBLabelImageSize';
    }

    init() {
        const editor = this.editor;
        const t = editor.t;

        //The kind of the selected image: null, "barcode", "part-image" or "image"
        this.set('selectedKind', null);
        this.listenTo(editor.model.document, 'change', () => this.set('selectedKind', this._getKind(this._getSelectedImage())));

        editor.ui.componentFactory.add('partdb_image_size', locale => {
            const dropdown = createDropdown(locale);
            dropdown.buttonView.set({label: t('Size'), withText: true});
            dropdown.buttonView.set('tooltip', t('Set the size of the image'));
            dropdown.bind('isEnabled').to(this, 'selectedKind', kind => kind === 'part-image' || kind === 'image');
            //The size of barcodes is set by the barcode size option of the label, so the dropdown is hidden for them
            dropdown.set('isHidden', false);
            dropdown.bind('isHidden').to(this, 'selectedKind', kind => kind === 'barcode');
            dropdown.extendTemplate({attributes: {class: [dropdown.bindTemplate.if('isHidden', 'ck-hidden')]}});

            const form = new ImageSizeFormView(locale);
            dropdown.panelView.children.add(form);

            let initial = null;

            //Show the current size, when the dropdown is opened
            dropdown.on('change:isOpen', (evt, name, isOpen) => {
                if (!isOpen) {
                    return;
                }
                const size = this._getDisplayedSize(this._getSelectedImage());
                initial = size ? {width: (size.width / PX_PER_MM).toFixed(1), height: (size.height / PX_PER_MM).toFixed(1)} : null;
                form.widthInput.fieldView.value = initial?.width ?? '';
                form.heightInput.fieldView.value = initial?.height ?? '';
                form.widthInput.fieldView.focus();
            });

            this.listenTo(form, 'submit', () => {
                const widthValue = form.widthInput.fieldView.element.value;
                const heightValue = form.heightInput.fieldView.element.value;
                //For images with a fixed aspect ratio, use the dimension that was changed
                const heightChanged = initial !== null && heightValue !== initial.height && widthValue === initial.width;
                this._setSize(parseFloat(widthValue) * PX_PER_MM, parseFloat(heightValue) * PX_PER_MM, heightChanged);
                dropdown.isOpen = false;
                editor.editing.view.focus();
            });

            return dropdown;
        });
    }

    _getSelectedImage() {
        return getSelectedImage(this.editor);
    }

    _getKind(image) {
        return getImageKind(image);
    }

    /**
     * Returns the natural size (in px) of the given image: From its width/height attributes, or from the loaded image
     * in the editor.
     */
    _getNaturalSize(image) {
        const width = parseFloat(image.getAttribute('width'));
        const height = parseFloat(image.getAttribute('height'));
        if (width > 0 && height > 0) {
            return {width, height};
        }

        const viewElement = this.editor.editing.mapper.toViewElement(image);
        const domElement = viewElement ? this.editor.editing.view.domConverter.mapViewToDom(viewElement) : null;
        const img = domElement?.tagName === 'IMG' ? domElement : domElement?.querySelector('img');
        if (img?.naturalWidth > 0 && img?.naturalHeight > 0) {
            return {width: img.naturalWidth, height: img.naturalHeight};
        }

        return {width: DEFAULT_SIZE, height: DEFAULT_SIZE};
    }

    /**
     * Returns the size (in px) of the given image, like it is shown on the label: The resized width (set via the resize
     * handles), with the aspect ratio of the image.
     */
    _getDisplayedSize(image) {
        if (!image) {
            return null;
        }
        const natural = this._getNaturalSize(image);
        const resizedWidth = String(image.getAttribute('resizedWidth') ?? '');
        const width = resizedWidth.endsWith('px') ? parseFloat(resizedWidth) : natural.width;

        return {width, height: width * natural.height / natural.width};
    }

    /**
     * Sets the size (in px) of the selected image.
     */
    _setSize(width, height, heightChanged) {
        const image = this._getSelectedImage();
        const kind = this._getKind(image);
        if (!image || !(width > 0) || !(height > 0)) {
            return;
        }

        const round = (value) => Math.round(value * 100) / 100;

        this.editor.model.change(writer => {
            if (kind === 'part-image') {
                setPartImagePlaceholderSize(writer, image, width, height);
            } else if (kind === 'image') {
                //Normal images keep their aspect ratio, like with the resize handles
                const natural = this._getNaturalSize(image);
                const resizedWidth = heightChanged ? height * natural.width / natural.height : width;
                writer.setAttribute('resizedWidth', round(resizedWidth) + 'px', image);
            }
            writer.removeAttribute('resizedHeight', image);
        });
    }
}
