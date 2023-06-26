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

// Main CSS files
//import '../css/app.css';

import '../css/app/layout.css';
import '../css/app/helpers.css';
import '../css/app/tables.css';
import '../css/app/bs-overrides.css';
import '../css/app/treeview.css';
import '../css/app/images.css';

// start the Stimulus application
import '../bootstrap';

// Need jQuery? Install it with "yarn add jquery", then uncomment to require it.
const $ = require('jquery');

//Only include javascript
import '@fortawesome/fontawesome-free/css/all.css'

require('bootstrap');

import "./error_handler";
import "./tab_remember";
import "./register_events";
import "./tristate_checkboxes";

//Define jquery globally
window.$ = window.jQuery = require("jquery")