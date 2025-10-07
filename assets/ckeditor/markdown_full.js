/**
 * @license Copyright (c) 2014-2022, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md or https://ckeditor.com/legal/ckeditor-oss-license
 */
import {ClassicEditor} from 'ckeditor5';
import {Alignment} from 'ckeditor5';
import {Autoformat} from 'ckeditor5';
import {Base64UploadAdapter} from 'ckeditor5';
import {BlockQuote} from 'ckeditor5';
import {Bold} from 'ckeditor5';
import {Code} from 'ckeditor5';
import {CodeBlock} from 'ckeditor5';
import {Essentials} from 'ckeditor5';
import {FindAndReplace} from 'ckeditor5';
import {FontBackgroundColor} from 'ckeditor5';
import {FontColor} from 'ckeditor5';
import {FontFamily} from 'ckeditor5';
import {FontSize} from 'ckeditor5';
import {GeneralHtmlSupport} from 'ckeditor5';
import {Heading} from 'ckeditor5';
import {Highlight} from 'ckeditor5';
import {HorizontalLine} from 'ckeditor5';
import {HtmlComment} from 'ckeditor5';
import {HtmlEmbed} from 'ckeditor5';
import {Image} from 'ckeditor5';
import {ImageResize} from 'ckeditor5';
import {ImageStyle} from 'ckeditor5';
import {ImageToolbar} from 'ckeditor5';
import {ImageUpload} from 'ckeditor5';
import {Indent} from 'ckeditor5';
import {IndentBlock} from 'ckeditor5';
import {Italic} from 'ckeditor5';
import {Link} from 'ckeditor5';
import {LinkImage} from 'ckeditor5';
import {List} from 'ckeditor5';
import {ListProperties} from 'ckeditor5';
import {Markdown} from 'ckeditor5';
import {MediaEmbed} from 'ckeditor5';
import {MediaEmbedToolbar} from 'ckeditor5';
import {Paragraph} from 'ckeditor5';
import {PasteFromOffice} from 'ckeditor5';
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
import {Table} from 'ckeditor5';
import {TableCaption} from 'ckeditor5';
import {TableCellProperties} from 'ckeditor5';
import {TableColumnResize} from 'ckeditor5';
import {TableProperties} from 'ckeditor5';
import {TableToolbar} from 'ckeditor5';
import {Underline} from 'ckeditor5';
import {WordCount} from 'ckeditor5';
import {EditorWatchdog} from 'ckeditor5';
import {TodoList} from 'ckeditor5';

import ExtendedMarkdown from "./plugins/extendedMarkdown.js";
import SpecialCharactersGreek from "./plugins/special_characters_emoji";
import {Mention, Emoji} from "ckeditor5";

class Editor extends ClassicEditor {}

// Plugins to include in the build.
Editor.builtinPlugins = [
    Autoformat,
    Base64UploadAdapter,
    BlockQuote,
    Bold,
    Code,
    CodeBlock,
    Essentials,
    FindAndReplace,
    FontBackgroundColor,
    FontColor,
    FontSize,
    GeneralHtmlSupport,
    Heading,
    Highlight,
    HorizontalLine,
    Image,
    ImageResize,
    ImageStyle,
    ImageToolbar,
    ImageUpload,
    Indent,
    IndentBlock,
    Italic,
    Link,
    LinkImage,
    List,
    //MediaEmbed,
    //MediaEmbedToolbar,
    Paragraph,
    PasteFromOffice,
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
    Table,
    TableProperties,
    TableToolbar,
    Underline,
    TodoList,

    Mention, Emoji,

    //Our own extensions
    ExtendedMarkdown,
    SpecialCharactersGreek
];

// Editor configuration.
Editor.defaultConfig = {
    toolbar: {
        items: [
            'heading',
            '|',
            'bold',
            'italic',
            'underline',
            'strikethrough',
            'subscript',
            'superscript',
            'removeFormat',
            'highlight',
            '|',
            'fontBackgroundColor',
            'fontColor',
            'fontSize',
            '|',
            'link',
            'bulletedList',
            'numberedList',
            'outdent',
            'indent',
            '|',
            'specialCharacters',
            "emoji",
            'horizontalLine',
            '|',
            'imageUpload',
            'blockQuote',
            'insertTable',
            //'mediaEmbed',
            'code',
            'codeBlock',
            'todoList',
            '|',
            'undo',
            'redo',
            'findAndReplace',
            'sourceEditing',
        ],
        shouldNotGroupWhenFull: true
    },
    language: 'en',
    image: {
        toolbar: [
            'imageTextAlternative',
            'imageStyle:inline',
            'imageStyle:block',
            'imageStyle:side',
            'linkImage'
        ]
    },
    table: {
        contentToolbar: [
            'tableColumn',
            'tableRow',
            'mergeTableCells',
            'tableProperties'
        ]
    },
    list: {
        properties: {
            styles: false,

        }
    }
};

export default { Editor, EditorWatchdog };
