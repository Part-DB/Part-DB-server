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

import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';

import '@fortawesome/fontawesome-free/css/all.css'

import 'datatables.net-bs4/css/dataTables.bootstrap4.css'
import 'datatables.net-buttons-bs4/css/buttons.bootstrap4.css'
import 'datatables.net-fixedheader-bs4/css/fixedHeader.bootstrap4.css'
import 'datatables.net-select-bs4/css/select.bootstrap4.css'
import 'bootstrap-select/dist/css/bootstrap-select.css'

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
require('bootstrap-select')


//Define jquery globally
window.$ = window.jQuery = require("jquery");

//console.log('Hello Webpack Encore! Edit me in assets/js/app.js');
