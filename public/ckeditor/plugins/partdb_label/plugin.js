/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/*
 * Placeholder logic inspired by CKEDITOR placeholder plugin (https://github.com/ckeditor/ckeditor4/blob/master/plugins/placeholder/plugin.js)
 */

const PLACEHOLDERS = {
    part: {
        label: 'section.part',
        entries: [
            ['[[ID]]', 'part.id'],
            ['[[NAME]]', 'part.name'],
            ['[[CATEGORY]]', 'part.category'],
            ['[[CATEGORY_FULL]]', 'part.category_full'],
            ['[[MANUFACTURER]]', 'part.manufacturer'],
            ['[[MANUFACTURER_FULL]]', 'part.manufacturer_full'],
            ['[[FOOTPRINT]]', 'part.footprint'],
            ['[[FOOTPRINT_FULL]]', 'part.footprint'],
            ['[[MASS]]', 'part.mass'],
            ['[[MPN]]', 'part.mpn'],
            ['[[TAGS]]', 'part.tags'],
            ['[[M_STATUS]]', 'part.status'],
            ['[[DESCRIPTION]]', 'part.description'],
            ['[[DESCRIPTION_T]]', 'part.description_t'],
            ['[[COMMENT]]', 'part.comment'],
            ['[[COMMENT_T]]', 'part.comment_t'],
            ['[[LAST_MODIFIED]]', 'part.last_modified'],
            ['[[CREATION_DATE]]', 'part.creation_date'],
        ]
    },
    part_lot: {
        label: 'section.part_lot',
        entries: [
            ['[[LOT_ID]]', 'lot.id'],
            ['[[LOT_NAME]]', 'lot.name'],
            ['[[LOT_COMMENT]]', 'lot.comment'],
            ['[[EXPIRATION_DATE]]', 'lot.expiration_date'],
            ['[[AMOUNT]]', 'lot.amount'],
            ['[[LOCATION]]', 'lot.location'],
            ['[[LOCATION_FULL]]', 'lot.location_full'],
        ]
    },
    storelocation: {
        label: 'section.storelocation',
        entries: [
            ['[[ID]]', 'storelocation.id'],
            ['[[NAME]]', 'storelocation.name'],
            ['[[FULL_PATH]]', 'storelocation.full_path'],
            ['[[PARENT]]', 'storelocation.parent_name'],
            ['[[PARENT_FULL_PATH]]', 'storelocation.parent_full_path'],
            ['[[COMMENT]]', 'storelocation.comment'],
            ['[[COMMENT_T]]', 'storelocation.comment_t'],
            ['[[LAST_MODIFIED]]', 'storelocation.last_modified'],
            ['[[CREATION_DATE]]', 'storelocation.creation_date'],
        ]
    },
    global: {
        label: 'section.global',
        entries: [
            ['[[USERNAME]]', 'global.username'],
            ['[[USERNAME_FULL]]', 'global.username_full'],
            ['[[DATETIME]]', 'global.datetime'],
            ['[[DATE]]', 'global.date'],
            ['[[TIME]]', 'global.time'],
            ['[[INSTALL_NAME]]', 'global.install_name'],
            ['[[TYPE]]', 'global.type']
        ],
    },
};

function findLabelForPlaceholder(search)
{
    for (var group in PLACEHOLDERS) {
        var entries = PLACEHOLDERS[group].entries;
        for (var placeholder in entries) {
            if (entries[placeholder][0] == search) {
                return entries[placeholder][1];
            }
        }
    }
}

//Dont escape text inside of twig blocks
CKEDITOR.config.protectedSource.push(/\{\{[\s\S]*?\}\}/g);
CKEDITOR.config.protectedSource.push(/\{\%[\s\S]*?%\}/g);

CKEDITOR.plugins.add('partdb_label', {
    hidpi: true,
    icons: 'placeholder',
    lang: ['en', 'de'],
    init: function (editor) {
        var config = editor.config,
            lang = editor.lang.partdb_label;

        var pluginDirectory = this.path;
        editor.addContentsCss( pluginDirectory + 'styles/style.css' );

        // Put ur init code here.
        editor.widgets.add( 'placeholder', {
            // Widget code.
            pathName: lang.label,
            // We need to have wrapping element, otherwise there are issues in
            // add dialog.
            template: '<span class="cke_placeholder">[[]]</span>',

            downcast: function() {
                return new CKEDITOR.htmlParser.text( '[[' + this.data.name + ']]' );
            },

            init: function() {
                // Note that placeholder markup characters are stripped for the name.
                this.setData( 'name', this.element.getText().slice( 2, -2 ) );
            },

            data: function() {
                this.element.setText( '[[' + this.data.name + ']]' );
                var title = findLabelForPlaceholder( '[[' + this.data.name + ']]');
                if (lang[title]) {
                    title = lang[title];
                }
                this.element.setAttribute('title', title);
            },

            getLabel: function() {
                return this.editor.lang.widget.label.replace( /%1/, this.data.name + ' ' + this.pathName );
            }
        } );

        editor.ui.addRichCombo('Placeholders', {
            label: lang.label,
            title: lang.title,
            allowedContent: 'abbr[title]',
            panel: {
                css: [ CKEDITOR.skin.getPath( 'editor' ) ].concat( config.contentsCss ),
                multiSelect: false,
                attributes: { 'aria-label': lang.title }
            },
            init: function () {
                for (var group in PLACEHOLDERS) {
                    var localized_group = PLACEHOLDERS[group].label;
                    if (lang[localized_group]) {
                        localized_group = lang[localized_group];
                    }
                    this.startGroup(localized_group);
                    var entries = PLACEHOLDERS[group].entries;
                    for (var placeholder in entries) {
                        var localized_placeholder = entries[placeholder][1];
                        if (lang[localized_placeholder]) {
                            localized_placeholder = lang[localized_placeholder];
                        }
                        this.add(entries[placeholder][0], localized_placeholder, entries[placeholder][0])
                    }
                }
            },
            onClick: function(value) {
                editor.focus();
                editor.fire('saveSnapshot');
                editor.insertText(value);
            }
        });
    },
    afterInit: function( editor ) {
        var placeholderReplaceRegex = /\[\[([^\[\]])+\]\]/g;

        editor.dataProcessor.dataFilter.addRules({
            text: function (text, node) {
                var dtd = node.parent && CKEDITOR.dtd[node.parent.name];

                // Skip the case when placeholder is in elements like <title> or <textarea>
                // but upcast placeholder in custom elements (no DTD).
                if (dtd && !dtd.span)
                    return;

                return text.replace(placeholderReplaceRegex, function (match) {
                    // Creating widget code.
                    var widgetWrapper = null,
                        innerElement = new CKEDITOR.htmlParser.element('span', {
                            'class': 'cke_placeholder'
                        });

                    // Adds placeholder identifier as innertext.
                    innerElement.add(new CKEDITOR.htmlParser.text(match));
                    widgetWrapper = editor.widgets.wrapElement(innerElement, 'placeholder');

                    // Return outerhtml of widget wrapper so it will be placed
                    // as replacement.
                    return widgetWrapper.getOuterHtml();
                });
            }
        });
    }
});