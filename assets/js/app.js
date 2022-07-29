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
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you require will output into a single css file (app.css in this case)

// Main CSS files
import '../css/app.css';

// start the Stimulus application
import '../bootstrap';

// Need jQuery? Install it with "yarn add jquery", then uncomment to require it.
const $ = require('jquery');

//Only include javascript
import '@fortawesome/fontawesome-free/css/all.css'

require('bootstrap');

import "./sidebar"
import "./datatables";
import "./error_handler";
import "./tab_remember";
import "./register_events";
import "./tristate_checkboxes";

//Define jquery globally
window.$ = window.jQuery = require("jquery")


/**

 require('bootstrap-select');
require('jquery-form');
require('corejs-typeahead/dist/typeahead.bundle.min');
window.Bloodhound =  require('corejs-typeahead/dist/bloodhound.js');

;



require('bootstrap-fileinput');

require('./datatables.js');

window.bootbox = require('bootbox');

require("marked");
window.DOMPurify = require("dompurify");

// Includes required for tag input
require('./tagsinput.js');
require('../css/tagsinput.css');

//Tristate checkbox support
require('./jquery.tristate.js');

require('darkmode-js');

//Equation rendering
require('katex');
window.renderMathInElement = require('katex/contrib/auto-render/auto-render').default;
import 'katex/dist/katex.css';

window.ClipboardJS = require('clipboard');

require('../ts_src/ajax_ui');
//import {ajaxUI} from "../ts_src/ajax_ui";

//window.ajaxUI = ajaxUI;

//Require all events;
require('../ts_src/event_listeners');


//Start AjaxUI AFTER all event has been registered
//$(document).ready(ajaxUI.start());

*/

//console.log('Hello Webpack Encore! Edit me in assets/js/app.js');