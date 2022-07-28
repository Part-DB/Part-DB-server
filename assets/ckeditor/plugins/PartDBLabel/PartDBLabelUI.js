import Plugin from '@ckeditor/ckeditor5-core/src/plugin';

require('./lang/en.js');

import { addListToDropdown, createDropdown } from '@ckeditor/ckeditor5-ui/src/dropdown/utils';

import Collection from '@ckeditor/ckeditor5-utils/src/collection';
import Model from '@ckeditor/ckeditor5-ui/src/model';

export default class PartDBLabelUI extends Plugin {
    init() {
        const editor = this.editor;
        const t = editor.t;

        // The "placeholder" dropdown must be registered among the UI components of the editor
        // to be displayed in the toolbar.
        editor.ui.componentFactory.add( 'partdb_label', locale => {
            const dropdownView = createDropdown( locale );

            // Populate the list in the dropdown with items.
            addListToDropdown( dropdownView, getDropdownItemsDefinitions(t) );

            dropdownView.buttonView.set( {
                // The t() function helps localize the editor. All strings enclosed in t() can be
                // translated and change when the language of the editor changes.
                label: t( 'part_db.label' ),
                tooltip: true,
                withText: true
            } );

            // Disable the placeholder button when the command is disabled.
            const command = editor.commands.get( 'partdb_label' );
            dropdownView.bind( 'isEnabled' ).to( command );

            // Execute the command when the dropdown item is clicked (executed).
            this.listenTo( dropdownView, 'execute', evt => {
                editor.execute( 'partdb_label', { value: evt.source.commandParam } );
                editor.editing.view.focus();
            } );

            return dropdownView;
        } );
    }
}

const PLACEHOLDERS = [
    {
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
    {
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
    {
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
    {
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
];


function getDropdownItemsDefinitions(t) {
    const itemDefinitions = new Collection();

    for ( const group of PLACEHOLDERS) {
        //Add group header
        itemDefinitions.add({
            'type': 'separator',
            model: new Model( {
                withText: true,
            })
        });

        itemDefinitions.add({
            type: 'button',
            model: new Model( {
                label: t(group.label),
                withText: true,
                isEnabled: false,
            } )
        });

        //Add group entries
        for ( const entry of group.entries) {
            const definition = {
                type: 'button',
                model: new Model( {
                    commandParam: entry[0],
                    label: t(entry[1]),
                    withText: true
                } ),
            };

            // Add the item definition to the collection.
            itemDefinitions.add( definition );
        }
    }

    return itemDefinitions;
}