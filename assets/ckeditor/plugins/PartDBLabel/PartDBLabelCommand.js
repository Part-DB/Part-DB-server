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

import {Command} from 'ckeditor5';

export default class PartDBLabelCommand extends Command {
    execute( { value } ) {
        const editor = this.editor;
        const selection = editor.model.document.selection;

        editor.model.change( writer => {
            // Create a <placeholder> elment with the "name" attribute (and all the selection attributes)...
            const placeholder = writer.createElement( 'partdb_label', {
                ...Object.fromEntries( selection.getAttributes() ),
                name: value
            } );

            // ... and insert it into the document.
            editor.model.insertContent( placeholder );

            // Put the selection on the inserted element.
            writer.setSelection( placeholder, 'on' );
        } );
    }

    refresh() {
        const model = this.editor.model;
        const selection = model.document.selection;

        const isAllowed = model.schema.checkChild( selection.focus.parent, 'partdb_label' );

        this.isEnabled = isAllowed;
    }
}
