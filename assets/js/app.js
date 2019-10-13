/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you require will output into a single css file (app.css in this case)

require('../css/app.css');

// Need jQuery? Install it with "yarn add jquery", then uncomment to require it.
const $ = require('jquery');

//Only include javascript



import '@fortawesome/fontawesome-free/css/all.css'

import 'datatables.net-bs4/css/dataTables.bootstrap4.css'
import 'datatables.net-buttons-bs4/css/buttons.bootstrap4.css'
import 'datatables.net-fixedheader-bs4/css/fixedHeader.bootstrap4.css'
import 'datatables.net-select-bs4/css/select.bootstrap4.css'
import 'bootstrap-select/dist/css/bootstrap-select.css'

import "patternfly-bootstrap-treeview/src/css/bootstrap-treeview.css"

import "bootstrap-fileinput/css/fileinput.css"

require('bootstrap');

//require( 'jszip' );
//#require( 'pdfmake' );
require( 'datatables.net-bs4' );
require( 'datatables.net-buttons-bs4' );
require( 'datatables.net-buttons/js/buttons.colVis.js' );
require( 'datatables.net-buttons/js/buttons.html5.js' );
require( 'datatables.net-buttons/js/buttons.print.js' );
//require( 'datatables.net-colreorder-bs4' )();
require( 'datatables.net-fixedheader-bs4' );
require( 'datatables.net-select-bs4' );
require('datatables.net-colreorder-bs4');
require('bootstrap-select');
require('jquery-form');
require('corejs-typeahead/dist/typeahead.bundle.min');
window.Bloodhound =  require('corejs-typeahead/dist/bloodhound.js');

//Define jquery globally
window.$ = window.jQuery = require("jquery");

require('patternfly-bootstrap-treeview/src/js/bootstrap-treeview');

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

require('../ts_src/ajax_ui');
import {ajaxUI} from "../ts_src/ajax_ui";

window.ajaxUI = ajaxUI;

//Require all events;
require('../ts_src/event_listeners');


//Start AjaxUI AFTER all event has been registered
$(document).ready(ajaxUI.start());



//console.log('Hello Webpack Encore! Edit me in assets/js/app.js');