'use strict';

//CSS
import 'bootstrap-select/dist/css/bootstrap-select.css'
import "bootstrap-fileinput/css/fileinput.css"

//JS
import "bootstrap-select";
import "./lib/jquery.tristate"
import "bootstrap-fileinput";

import 'corejs-typeahead';
const Bloodhound = require('corejs-typeahead/dist/bloodhound.js');
import './lib/tagsinput';



const RegisterEventHelper = class {
    constructor() {
        this.registerToasts();
        this.registerTooltips();
        this.registerFileInput();
        this.registerJumpToTopBtn();

        this.registerTriStateCheckboxes();

        this.registerBootstrapSelectPicker();

        this.registerSpecialCharInput();
        this.registerHoverPics();

        this.registerAutocompleteTagsinput();
    }

    registerLoadHandler(fn) {
        document.addEventListener('turbo:load', fn);
    }

    registerToasts() {
        this.registerLoadHandler(() =>  $(".toast").toast('show'));
    }

    registerTooltips() {
        this.registerLoadHandler(() => {
            $(".tooltip").remove();
            $('a[title], button[title], span[title], h6[title], h3[title], i.fas[title]')
                //@ts-ignore
                .tooltip("hide").tooltip({container: "body", placement: "auto", boundary: 'window'});
        });
    }

    registerHoverPics() {

    }

    registerFileInput() {
        this.registerLoadHandler(() => {
            $(".file").fileinput();
        });
    }

    registerJumpToTopBtn() {
        this.registerLoadHandler(() => {
            $(window).scroll(function () {
                if ($(this).scrollTop() > 50) {
                    $('#back-to-top').fadeIn();
                } else {
                    $('#back-to-top').fadeOut();
                }
            });
            // scroll body to 0px on click
            $('#back-to-top').click(function () {
                $('#back-to-top').tooltip('hide');
                $('body,html').animate({
                    scrollTop: 0
                }, 800);
                return false;
            }).tooltip();
        });
    }

    registerTriStateCheckboxes() {
        this.registerLoadHandler(() => {
            $(".tristate").tristate( {
                checked:            "true",
                unchecked:          "false",
                indeterminate:      "indeterminate",
            });

            $('.permission_multicheckbox:checkbox').change(function() {
                //Find the other checkboxes in this row, and change their value
                var $row = $(this).parents('tr');

                //@ts-ignore
                var new_state = $(this).tristate('state');

                //@ts-ignore
                $('.tristate:checkbox', $row).tristate('state', new_state);
            });
        })
    }

    registerBootstrapSelectPicker() {
        this.registerLoadHandler(() => {
            $(".selectpicker").selectpicker({
                dropdownAlignRight: 'auto',
                container: '#content',
            });
        });
    }

    registerAutocompleteTagsinput() {
        this.registerLoadHandler(() => {
            $('input.tagsinput').each(function() {

                //Use typeahead if an autocomplete url was specified.
                if($(this).data('autocomplete')) {

                    //@ts-ignore
                    var engine = new Bloodhound({
                        //@ts-ignore
                        datumTokenizer: Bloodhound.tokenizers.obj.whitespace(''),
                        //@ts-ignore
                        queryTokenizer: Bloodhound.tokenizers.obj.whitespace(''),
                        remote: {
                            url: $(this).data('autocomplete'),
                            wildcard: 'QUERY'
                        }
                    });

                    //@ts-ignore
                    $(this).tagsinput({
                        typeaheadjs: {
                            name: 'tags',
                            source: engine.ttAdapter()
                        }
                    });


                } else { //Init tagsinput without typeahead
                    //@ts-ignore
                    $(this).tagsinput();
                }
            })
        });
    }

    registerSpecialCharInput() {
        this.registerLoadHandler(() => {
            //@ts-ignore
            $("input[type=text], textarea, input[type=search]").unbind("keydown").keydown(function (event) {
                let greek = event.altKey;

                let greek_char = "";
                if (greek){
                    switch(event.key) {
                        case "w": //Omega
                            greek_char = '\u2126';
                            break;
                        case "u":
                        case "m": //Micro
                            greek_char = "\u00B5";
                            break;
                        case "p": //Phi
                            greek_char = "\u03C6";
                            break;
                        case "a": //Alpha
                            greek_char = "\u03B1";
                            break;
                        case "b": //Beta
                            greek_char = "\u03B2";
                            break;
                        case "c": //Gamma
                            greek_char = "\u03B3";
                            break;
                        case "d": //Delta
                            greek_char = "\u03B4";
                            break;
                        case "l": //Pound
                            greek_char = "\u00A3";
                            break;
                        case "y": //Yen
                            greek_char = "\u00A5";
                            break;
                        case "o": //Yen
                            greek_char = "\u00A4";
                            break;
                        case "1": //Sum symbol
                            greek_char = "\u2211";
                            break;
                        case "2": //Integral
                            greek_char = "\u222B";
                            break;
                        case "3": //Less-than or equal
                            greek_char = "\u2264";
                            break;
                        case "4": //Greater than or equal
                            greek_char = "\u2265";
                            break;
                        case "5": //PI
                            greek_char = "\u03c0";
                            break;
                        case "q": //Copyright
                            greek_char = "\u00A9";
                            break;
                        case "e": //Euro
                            greek_char = "\u20AC";
                            break;
                    }

                    if(greek_char=="") return;

                    let $txt = $(this);
                    //@ts-ignore
                    let caretPos = $txt[0].selectionStart;
                    let textAreaTxt = $txt.val().toString();
                    $txt.val(textAreaTxt.substring(0, caretPos) + greek_char + textAreaTxt.substring(caretPos) );

                }
            });
            //@ts-ignore
            this.greek_once = true;
        })
    }
}

export default new RegisterEventHelper();