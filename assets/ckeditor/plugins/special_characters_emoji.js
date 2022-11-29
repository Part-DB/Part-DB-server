/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

import SpecialCharacters from '@ckeditor/ckeditor5-special-characters/src/specialcharacters';
import SpecialCharactersEssentials from '@ckeditor/ckeditor5-special-characters/src/specialcharactersessentials';

import Plugin from '@ckeditor/ckeditor5-core/src/plugin';

const emoji = require('emoji.json');

export default class SpecialCharactersEmoji extends Plugin {

    init() {
        const editor = this.editor;
        const specialCharsPlugin = editor.plugins.get('SpecialCharacters');

        specialCharsPlugin.addItems('Emoji', this.getEmojis());
    }

    getEmojis() {
        //Map our emoji data to the format the plugin expects
        return emoji.map(emoji => {
            return {
                title: emoji.name,
                character: emoji.char
            };
        });
    }
}