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

/**
 * Converts images of existing labels, which use the "side" image style (offered by older versions of the label editor),
 * to the "Wrap text: right" style (alignRight), which replaced it. The images are then saved with the new style.
 * The label generator renders both styles the same way, so labels which were not edited again look the same.
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
}
