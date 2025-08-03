import {ClassicEditor} from 'ckeditor5'
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
import PartDBLabel from "./plugins/PartDBLabel/PartDBLabel";

class Editor extends ClassicEditor {}

// Plugins to include in the build.
Editor.builtinPlugins = [
    Alignment,
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
    FontFamily,
    FontSize,
    GeneralHtmlSupport,
    Heading,
    Highlight,
    HorizontalLine,
    HtmlComment,
    HtmlEmbed,
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
    ListProperties,
    MediaEmbed,
    MediaEmbedToolbar,
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
    TableCaption,
    TableCellProperties,
    TableColumnResize,
    TableProperties,
    TableToolbar,
    Underline,
    WordCount,

    PartDBLabel
];

// Editor configuration.
Editor.defaultConfig = {
    toolbar: {
        items: [
            'heading',
            'alignment',
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
            'fontFamily',
            'link',
            'bulletedList',
            'numberedList',
            'outdent',
            'indent',
            '|',
            'specialCharacters',
            'horizontalLine',
            '|',
            'imageUpload',
            'blockQuote',
            'insertTable',
            'mediaEmbed',
            'code',
            'codeBlock',
            'htmlEmbed',
            '|',
            'undo',
            'redo',
            'findAndReplace',
            'sourceEditing',
            '|',
            'partdb_label',
        ],
        shouldNotGroupWhenFull: true
    },
    language: 'en',
    fontFamily: {
        options: [
            'default',
            'DejaVu Sans Mono, monospace',
            'DejaVu Sans, sans-serif',
            'DejaVu Serif, serif',
            'Helvetica, Arial, sans-serif',
            'Times New Roman, Times, serif',
            'Courier New, Courier, monospace',
            'Unifont, monospace',
        ],
        supportAllValues: true
    },
    'fontSize': {
        options: [
            'default',
            6,
            7,
            8,
            9,
            10,
            11,
            12,
            13,
            14,
            15,
            16,
            17,
            18,
            19,
            20,
            21,
        ],
        supportAllValues: true
    },
    // Allow all HTML features for our labels
    htmlSupport: {
        allow: [
            {
                name: /.*/,
                attributes: true,
                classes: true,
                styles: true
            }
        ],
        disallow: [
            //Some rudimentary protection against XSS, even if it is not really needed as this is only parsed by DOMHTML which does not support any kind of script execution.
            {
                name: /^(head|body|html|script)$/i,
            },
            {
                name: /.*/,
                attributes: /^on.*/i
            }
        ]
    },
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
            'tableCellProperties',
            'tableProperties'
        ]
    },
};

export default { Editor, EditorWatchdog };
