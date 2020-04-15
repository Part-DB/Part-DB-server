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

const PLACEHOLDERS = {
    part: {
        label: 'section.part',
        entries: [
            ['%%ID%%', 'part.id'],
            ['%%NAME%%', 'part.name'],
            ['%%CATEGORY%%', 'part.category'],
            ['%%CATEGORY_FULL', 'part.category_full'],
            ['%%MANUFACTURER', 'part.manufacturer'],
            ['%%MANUFACTURER_FULL', 'part.manufacturer_full'],
            ['%%FOOTPRINT%%', 'part.footprint'],
            ['%%FOOTPRINT_FULL%%', 'part.footprint'],
            ['%%MASS%%', 'part.mass'],
            ['%%MPN%%', 'part.mpn'],
            ['%%TAGS%%', 'part.tags'],
            ['%%M_STATUS%%', 'part.status'],
            ['%%DESCRIPTION%%', 'part.description'],
            ['%%DESCRIPTION_T%%', 'part.description_t'],
            ['%%COMMENT%%', 'part.comment'],
            ['%%COMMENT_T%%', 'part.comment_t'],
            ['%%LAST_MODIFIED%%', 'part.last_modified'],
            ['%%CREATION_DATE%%', 'part.creation_date'],
        ]
    },
    global: {
        label: 'section.global',
        entries: [
            ['%%USERNAME%%', 'global.username'],
            ['%%USERNAME_FULL%%', 'global.username_full'],
            ['%%DATETIME%%', 'global.datetime'],
            ['%%DATE%%', 'global.date'],
            ['%%TIME%%', 'global.time'],
            ['%%INSTALL_NAME%%', 'global.install_name'],
            ['%%TYPE%%', 'global.type']
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

CKEDITOR.plugins.add('partdb_label', {
    hidpi: true,
    icons: 'placeholder',
    lang: ['en', 'de'],
    init: function (editor) {
        var config = editor.config,
            lang = editor.lang.partdb_label;

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
                var title = findLabelForPlaceholder(value);
                if (lang[title]) {
                    title = lang[title];
                }
                editor.insertHtml('<abbr title="' + title + '">' + value + '</abbr>' + ' ', 'text');
            }
        });
    }
});