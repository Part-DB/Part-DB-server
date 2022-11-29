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

import PartDBLabelUI from "./PartDBLabelUI";
import PartDBLabelEditing from "./PartDBLabelEditing";

import "./PartDBLabel.css";

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";

export default class PartDBLabel extends Plugin {
    static get requires() {
        return [PartDBLabelUI, PartDBLabelEditing];
    }

    static get pluginName() {
        return 'PartDBLabel';
    }
}