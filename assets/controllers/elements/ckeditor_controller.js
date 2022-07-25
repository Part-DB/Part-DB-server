import {Controller} from "@hotwired/stimulus";

import { default as FullEditor } from "../../ckeditor/markdown_full";
import { default as SingleLineEditor} from "../../ckeditor/markdown_single_line";
import { default as HTMLEditor } from "../../ckeditor/html_full";

import EditorWatchdog from '@ckeditor/ckeditor5-watchdog/src/editorwatchdog';


/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        const mode = this.element.dataset.mode;
        const output_format = this.element.dataset.outputFormat;

        let EDITOR_TYPE = "Invalid";

        if(output_format == 'markdown') {
            if(mode == 'full') {
                EDITOR_TYPE = FullEditor['Editor'];
            } else if(mode == 'single_line') {
                EDITOR_TYPE = SingleLineEditor['Editor'];
            }
        } else if(output_format == 'html') {
            EDITOR_TYPE = HTMLEditor['Editor'];
        } else {
            console.error("Unknown output format: " + output-format);
            return;
        }

        EDITOR_TYPE.create(this.element)
            .then(editor => {
                if(this.element.disabled) {
                    editor.enableReadOnlyMode("readonly");
                }

                console.log(editor);
            })
            .catch(error => {
                console.error(error);
            });

       /* const watchdog = new EditorWatchdog();
        watchdog.setCreator((elementOrData, editorConfig) => {
            return EDITOR_TYPE.create(elementOrData, editorConfig)
                .then(editor => {
                    if(this.element.disabled) {
                        editor.enableReadOnlyMode("readonly");
                    }

                    console.log(editor);
                })
                .catch(error => {
                    console.error(error);
                });
        });

        watchdog.create(this.element, {

        });*/
    }
}