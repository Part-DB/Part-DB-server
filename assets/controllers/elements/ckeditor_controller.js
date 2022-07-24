import {Controller} from "@hotwired/stimulus";

import { default as FullEditor } from "../../ckeditor/markdown_full";
import { default as SingleLineEditor} from "../../ckeditor/markdown_single_line";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        const mode = this.element.dataset.mode;
        const output_format = this.element.dataset.outputFormat;

        let EDITOR_TYPE = "Invalid";

        if(output_format == 'markdown') {
            if(mode == 'full') {
                EDITOR_TYPE = FullEditor;
            } else if(mode == 'single_line') {
                EDITOR_TYPE = SingleLineEditor;
            }
        } else {
            console.error("Unknown output format: " + output-format);
            return;
        }

        this.editor = EDITOR_TYPE.create(this.element, {

        })
            .then(editor => {
                console.log(editor);
            })
            .catch(error => {
                console.error(error);
            });
    }
}