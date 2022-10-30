import {Controller} from "@hotwired/stimulus";

import { default as FullEditor } from "../../ckeditor/markdown_full";
import { default as SingleLineEditor} from "../../ckeditor/markdown_single_line";
import { default as HTMLLabelEditor } from "../../ckeditor/html_label";

import EditorWatchdog from '@ckeditor/ckeditor5-watchdog/src/editorwatchdog';

import "../../css/ckeditor.css";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        const mode = this.element.dataset.mode;

        let EDITOR_TYPE = "Invalid";

        switch (mode) {
            case "markdown-full":
                EDITOR_TYPE = FullEditor['Editor'];
                break;
            case "markdown-single_line":
                EDITOR_TYPE = SingleLineEditor['Editor'];
                break;
            case "html-label":
                EDITOR_TYPE = HTMLLabelEditor['Editor'];
                break;
            default:
                console.error("Unknown mode: " + mode);
                return;
        }

        const language = document.body.dataset.locale ?? "en";

        const config = {
            language: language,
        }

        const watchdog = new EditorWatchdog();
        watchdog.setCreator((elementOrData, editorConfig) => {
            return EDITOR_TYPE.create(elementOrData, editorConfig)
                .then(editor => {
                    if(this.element.disabled) {
                        editor.enableReadOnlyMode("readonly");
                    }

                    //Apply additional styles
                    const editor_div = editor.ui.view.element;
                    const new_classes = this.element.dataset.ckClass;
                    if (editor_div && new_classes) {
                        editor_div.classList.add(...new_classes.split(","));
                    }

                    console.log(editor);
                })
                .catch(error => {
                    console.error(error);
                });
        });

        watchdog.create(this.element, config).catch(error => {
            console.error(error);
        });
    }
}