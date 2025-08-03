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

import {Controller} from "@hotwired/stimulus";

import { default as FullEditor } from "../../ckeditor/markdown_full";
import { default as SingleLineEditor} from "../../ckeditor/markdown_single_line";
import { default as HTMLLabelEditor } from "../../ckeditor/html_label";

import {EditorWatchdog} from 'ckeditor5';

import "ckeditor5/ckeditor5.css";;
import "../../css/components/ckeditor.css";

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
            licenseKey: "GPL",
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

                    //This return is important! Otherwise we get mysterious errors in the console
                    //See: https://github.com/ckeditor/ckeditor5/issues/5897#issuecomment-628471302
                    return editor;
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
