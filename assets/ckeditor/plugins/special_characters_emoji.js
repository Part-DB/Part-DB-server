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