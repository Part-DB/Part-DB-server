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

import {Plugin} from 'ckeditor5';

export default class SingleLinePlugin extends Plugin {
    init() {
        const editor = this.editor;
        const view = editor.editing.view;
        const viewDocument = view.document;

        //Listen to enter presses
        this.listenTo( viewDocument, 'enter', ( evt, data ) => {
            //If user presses enter, prevent the enter action
            evt.stop();
        }, { priority: 'high' } );

        //And clipboard pastes
        this.listenTo( viewDocument, 'clipboardInput', ( evt, data ) => {
            let dataTransfer = data.dataTransfer;

            //Clean text input (replace newlines with spaces)
            let input = dataTransfer.getData("text");
            let cleaned = input.replace(/\r?\n/g, ' ');

            //We can not use the dataTransfer.setData method because the old object is somehow protected
            data.dataTransfer = new DataTransfer();
            data.dataTransfer.setData("text", cleaned);

        }, { priority: 'high' } );
    }
}
