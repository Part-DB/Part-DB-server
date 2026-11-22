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

/** The style of images inserted in the editor ("Wrap text: left") */
const NEW_IMAGE_STYLE = 'alignLeft';

/** The commands which insert new images (via the toolbar, an upload, or by dropping or pasting image files) */
const INSERT_COMMANDS = ['insertImage', 'uploadImage'];

/**
 * Handles the image styles of the label editor:
 *  - Converts images of existing labels, which use the "side" image style (offered by older versions of the label
 *    editor), to the "Wrap text: right" style (alignRight), which replaced it. The images are then saved with the new
 *    style. The label generator renders both styles the same way, so labels which were not edited again look the same.
 *  - Images inserted in the editor get the "Wrap text: left" style. Otherwise they would be block images without a style,
 *    which keep the default positioning of images of older labels (no wrapping, wide side margins), and which can not be
 *    selected in the image toolbar. Images of existing labels (and images moved or restored by undo) are not changed.
 */
export default class PartDBLabelImageStyles extends Plugin {
    static get pluginName() {
        return 'PartDBLabelImageStyles';
    }

    init() {
        //Runs after the upcast converter of ImageStyleEditing (priority "low"), which sets the style from the CSS class
        this.editor.data.upcastDispatcher.on('element:figure', (evt, data, conversionApi) => {
            if (!data.viewItem.hasClass('image-style-side') || !data.modelRange) {
                return;
            }

            const image = data.modelRange.start.nodeAfter;
            if (image?.is('element', 'imageBlock')) {
                conversionApi.writer.setAttribute('imageStyle', 'alignRight', image);
            }
        }, {priority: 'lowest'});
    }

    afterInit() {
        const editor = this.editor;

        //Whether one of the insert commands is executed. The images are inserted synchronously during the execution, so
        //the flag is reset afterward (also if the command is disabled or fails).
        let inserting = false;
        for (const name of INSERT_COMMANDS) {
            const command = editor.commands.get(name);
            if (command) {
                this.listenTo(command, 'execute', () => {
                    inserting = true;
                    queueMicrotask(() => inserting = false);
                }, {priority: 'highest'});
            }
        }

        //Part of the same change as the insertion, so that it is undone together with it
        editor.model.document.registerPostFixer(writer => {
            if (!inserting) {
                return false;
            }

            let changed = false;
            for (const change of editor.model.document.differ.getChanges()) {
                const image = change.type === 'insert' && change.name === 'imageBlock' ? change.position.nodeAfter : null;
                if (image?.is('element', 'imageBlock') && !image.hasAttribute('imageStyle')) {
                    writer.setAttribute('imageStyle', NEW_IMAGE_STYLE, image);
                    changed = true;
                }
            }
            return changed;
        });
    }
}
