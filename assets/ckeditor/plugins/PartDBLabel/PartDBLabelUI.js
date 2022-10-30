import Plugin from '@ckeditor/ckeditor5-core/src/plugin';

require('./lang/de.js');

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
                label: t( 'Label Placeholder' ),
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
        label: 'Part',
        entries: [
            ['[[ID]]', 'Database ID'],
            ['[[NAME]]', 'Part name'],
            ['[[CATEGORY]]', 'Category'],
            ['[[CATEGORY_FULL]]', 'Category (Full path)'],
            ['[[MANUFACTURER]]', 'Manufacturer'],
            ['[[MANUFACTURER_FULL]]', 'Manufacturer (Full path)'],
            ['[[FOOTPRINT]]', 'Footprint'],
            ['[[FOOTPRINT_FULL]]', 'Footprint (Full path)'],
            ['[[MASS]]', 'Mass'],
            ['[[MPN]]', 'Manufacturer Product Number (MPN)'],
            ['[[TAGS]]', 'Tags'],
            ['[[M_STATUS]]', 'Manufacturing status'],
            ['[[DESCRIPTION]]', 'Description'],
            ['[[DESCRIPTION_T]]', 'Description (plain text)'],
            ['[[COMMENT]]', 'Comment'],
            ['[[COMMENT_T]]', 'Comment (plain text)'],
            ['[[LAST_MODIFIED]]', 'Last modified datetime'],
            ['[[CREATION_DATE]]', 'Creation datetime'],
        ]
    },
    {
        label: 'Part lot',
        entries: [
            ['[[LOT_ID]]', 'Lot ID'],
            ['[[LOT_NAME]]', 'Lot name'],
            ['[[LOT_COMMENT]]', 'Lot comment'],
            ['[[EXPIRATION_DATE]]', 'Lot expiration date'],
            ['[[AMOUNT]]', 'Lot amount'],
            ['[[LOCATION]]', 'Storage location'],
            ['[[LOCATION_FULL]]', 'Storage location (Full path)'],
        ]
    },
    {
        label: 'Storage location',
        entries: [
            ['[[ID]]', 'Location ID'],
            ['[[NAME]]', 'Name'],
            ['[[FULL_PATH]]', 'Full path'],
            ['[[PARENT]]', 'Parent name'],
            ['[[PARENT_FULL_PATH]]', 'Parent full path'],
            ['[[COMMENT]]', 'Comment'],
            ['[[COMMENT_T]]', 'Comment (plain text)'],
            ['[[LAST_MODIFIED]]', 'Last modified datetime'],
            ['[[CREATION_DATE]]', 'Creation datetime'],
        ]
    },
    {
        label: 'Barcodes',
        entries: [
            ['[[1D_CONTENT]]', 'Content of the 1D barcodes (like Code 39)'],
            ['[[2D_CONTENT]]', 'Content of the 2D barcodes (QR codes)'],
            ['[[BARCODE_QR]]', 'QR code linking to this element'],
            ['[[BARCODE_C128]]', 'Code 128 barcode linking to this element'],
            ['[[BARCODE_C39]]', 'Code 39 barcode linking to this element'],
        ]
    },
    {
        label: 'Globals',
        entries: [
            ['[[USERNAME]]', 'Username'],
            ['[[USERNAME_FULL]]', 'Username (including name)'],
            ['[[DATETIME]]', 'Current datetime'],
            ['[[DATE]]', 'Current date'],
            ['[[TIME]]', 'Current time'],
            ['[[INSTALL_NAME]]', 'Instance name'],
            ['[[TYPE]]', 'Target type'],
            ['[[INSTANCE_URL]]', 'URL of this Part-DB instance']
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
                    tooltip: entry[0],
                    withText: true
                } ),
            };

            // Add the item definition to the collection.
            itemDefinitions.add( definition );
        }
    }

    return itemDefinitions;
}