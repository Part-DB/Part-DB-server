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