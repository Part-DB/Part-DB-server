/**
 * @license Copyright (c) 2014-2022, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */
import {ClassicEditor} from 'ckeditor5';
import {Autoformat} from 'ckeditor5';
import {AutoLink} from 'ckeditor5';
import {Bold} from 'ckeditor5';
import {Code} from 'ckeditor5';
import {Essentials} from 'ckeditor5';
import {FindAndReplace} from 'ckeditor5';
import {Highlight} from 'ckeditor5';
import {Italic} from 'ckeditor5';
import {Link} from 'ckeditor5';
import {Paragraph} from 'ckeditor5';
import {RemoveFormat} from 'ckeditor5';
import {SourceEditing} from 'ckeditor5';
import {SpecialCharacters} from 'ckeditor5';
import {SpecialCharactersArrows} from 'ckeditor5';
import {SpecialCharactersCurrency} from 'ckeditor5';
import {SpecialCharactersEssentials} from 'ckeditor5';
import {SpecialCharactersLatin} from 'ckeditor5';
import {SpecialCharactersMathematical} from 'ckeditor5';
import {SpecialCharactersText} from 'ckeditor5';
import {Strikethrough} from 'ckeditor5';
import {Subscript} from 'ckeditor5';
import {Superscript} from 'ckeditor5';
import {Underline} from 'ckeditor5';
import {EditorWatchdog} from 'ckeditor5';
import {Mention, Emoji} from "ckeditor5";

import ExtendedMarkdownInline from "./plugins/extendedMarkdownInline";
import SingleLinePlugin from "./plugins/singleLine";
import SpecialCharactersGreek from "./plugins/special_characters_emoji";

class Editor extends ClassicEditor {}

// Plugins to include in the build.
Editor.builtinPlugins = [
    Autoformat,
    AutoLink,
    Bold,
    Code,
    FindAndReplace,
    Highlight,
    Italic,
    Link,
    Paragraph,
    RemoveFormat,
    SourceEditing,
    SpecialCharacters,
    SpecialCharactersArrows,
    SpecialCharactersCurrency,
    SpecialCharactersEssentials,
    SpecialCharactersLatin,
    SpecialCharactersMathematical,
    SpecialCharactersText,
    Strikethrough,
    Subscript,
    Superscript,
    Underline,
    Essentials,

    ExtendedMarkdownInline,
    SingleLinePlugin,
    SpecialCharactersGreek,
    Mention, Emoji
];

// Editor configuration.
Editor.defaultConfig = {
    toolbar: {
        items: [
            'bold',
            'italic',
            'underline',
            'strikethrough',
            'subscript',
            'superscript',
            'removeFormat',
            'highlight',
            '|',
            'link',
            'code',
            'specialCharacters',
            'emoji',
            '|',
            'undo',
            'redo',
            'findAndReplace',
            'sourceEditing'
        ]
    },
    language: 'en'
};

export default { Editor, EditorWatchdog };
