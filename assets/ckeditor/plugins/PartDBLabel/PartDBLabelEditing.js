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

import Plugin from '@ckeditor/ckeditor5-core/src/plugin';
import PartDBLabelCommand from "./PartDBLabelCommand";

import { toWidget } from '@ckeditor/ckeditor5-widget/src/utils';
import Widget from '@ckeditor/ckeditor5-widget/src/widget';

export default class PartDBLabelEditing extends Plugin {
    static get requires() {                                                    // ADDED
        return [ Widget ];
    }

    init() {

        this._defineSchema();
        this._defineConverters();

        this.editor.commands.add('partdb_label', new PartDBLabelCommand( this.editor ) );
    }

    _defineSchema() {
        const schema = this.editor.model.schema;

        schema.register('partdb_label', {
            // Allow wherever text is allowed:
            allowWhere: '$text',

            // The placeholder will act as an inline node:
            isInline: true,

            // The inline widget is self-contained so it cannot be split by the caret and can be selected:
            isObject: true,

            allowAttributesOf: '$text',

            allowAttributes: [ 'name' ]
        });
    }

    _defineConverters() {
        const conversion = this.editor.conversion;

        conversion.for( 'upcast' ).elementToElement( {
            view: {
                name: 'span',
                classes: [ 'cke_placeholder' ]
            },
            model: ( viewElement, { writer: modelWriter } ) => {
                // Extract the "name" from "{name}".
                const name = viewElement.getChild( 0 ).data;

                return modelWriter.createElement( 'partdb_label', { name } );
            }
        } );

        conversion.for( 'editingDowncast' ).elementToElement( {
            model: 'partdb_label',
            view: ( modelItem, { writer: viewWriter } ) => {
                const widgetElement = createPlaceholderView( modelItem, viewWriter );

                // Enable widget handling on a placeholder element inside the editing view.
                return toWidget( widgetElement, viewWriter );
            }
        } );

        conversion.for( 'dataDowncast' ).elementToElement( {
            model: 'partdb_label',
            view: ( modelItem, { writer: viewWriter } ) => createPlaceholderView( modelItem, viewWriter )
        } );

        // Helper method for both downcast converters.
        function createPlaceholderView( modelItem, viewWriter ) {
            const name = modelItem.getAttribute( 'name' );

            const placeholderView = viewWriter.createContainerElement( 'span', {
                class: 'cke_placeholder'
            } );

            // Insert the placeholder name (as a text).
            const innerText = viewWriter.createText( name );
            viewWriter.insert( viewWriter.createPositionAt( placeholderView, 0 ), innerText );

            return placeholderView;
        }
    }

}