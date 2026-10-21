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
import {getFormField, PART_IMAGE_SRC_PREFIX, PART_IMAGE_ELEMENTS} from "./labelGeometry";

/** The size of a new placeholder in px */
export const DEFAULT_SIZE = 64;

/**
 * Creates the src of a placeholder with the given size (in px): A dashed box with a picture icon in its center.
 * The size of the box is stored as the size of the placeholder image itself (and as its width/height attributes), so that
 * it is kept by CKEditor and read by the label generator (see LabelHTMLGenerator::getPlaceholderSize()).
 */
export function createPlaceholderSrc(width, height) {
    const icon = Math.min(width, height) * 0.5;
    const x = (width - icon) / 2;
    const y = (height - icon) / 2;
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">`
        + `<rect x="1" y="1" width="${width - 2}" height="${height - 2}" fill="#f4f4f4" stroke="#888" stroke-width="2" stroke-dasharray="4 3"/>`
        + `<svg x="${x}" y="${y}" width="${icon}" height="${icon}" viewBox="0 0 32 24">`
        + '<rect x="1" y="1" width="30" height="22" fill="none" stroke="#888" stroke-width="2"/>'
        + '<circle cx="8" cy="7" r="3" fill="#888"/>'
        + '<path d="M2 22 L12 12 L19 19 L24 14 L30 20 L30 22 Z" fill="#888"/>'
        + '</svg></svg>';

    return PART_IMAGE_SRC_PREFIX + ';base64,' + btoa(svg);
}

/**
 * Sets the size (in px) of the given part image placeholder (inside a model.change() block). The size is stored as the
 * size of the placeholder image itself, so that CKEditor keeps it (resize styles with both width and height are dropped).
 */
export function setPartImagePlaceholderSize(writer, image, width, height) {
    width = Math.round(width * 100) / 100;
    height = Math.round(height * 100) / 100;
    writer.setAttribute('src', createPlaceholderSrc(width, height), image);
    writer.setAttribute('width', width, image);
    writer.setAttribute('height', height, image);
    writer.removeAttribute('resizedWidth', image);
    writer.removeAttribute('resizedHeight', image);
}

/**
 * Adds a "Part image" button to the label editor, which inserts a placeholder for the main image of the part
 * (the same image as in the parts table). Its size can be set via the "Size" dropdown (see PartDBLabelImageSize).
 * The placeholder can be positioned like any other image, and the image is fitted into it when the label is generated
 * (see LabelHTMLGenerator::placePartImage()).
 */
export default class PartDBLabelPartImage extends Plugin {
    static get pluginName() {
        return 'PartDBLabelPartImage';
    }

    init() {
        const editor = this.editor;
        const t = editor.t;

        //Only parts and part lots have a part image
        this.set('supportsPartImage', true);
        const elementField = getFormField(editor, 'supported_element');
        if (elementField) {
            const update = () => this.set('supportsPartImage', PART_IMAGE_ELEMENTS.includes(elementField.value));
            update();
            elementField.addEventListener('change', update);
        }

        editor.ui.componentFactory.add('partdb_part_image', locale => {
            const button = new ButtonView(locale);

            button.set({
                label: t('Part image'),
                withText: true,
            });

            button.bind('tooltip').to(this, 'supportsPartImage', supported => supported
                ? t('Insert the image of the part. It is fitted into the placeholder, which can be resized.')
                : t('Only labels for parts and part lots can contain the part image.'));

            const command = editor.commands.get('insertImage');
            button.bind('isEnabled').to(command, 'isEnabled', this, 'supportsPartImage',
                (commandEnabled, supported) => commandEnabled && supported);

            this.listenTo(button, 'execute', () => {
                editor.execute('insertImage', {source: {
                    src: createPlaceholderSrc(DEFAULT_SIZE, DEFAULT_SIZE), alt: 'Part image',
                    width: DEFAULT_SIZE, height: DEFAULT_SIZE,
                }});
                editor.editing.view.focus();
            });

            return button;
        });
    }
}
