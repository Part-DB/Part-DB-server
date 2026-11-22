/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

import {Plugin, Dialog, View} from 'ckeditor5';
import DOMPurify from 'dompurify';

/**
 * Sanitizes the HTML of a snippet for its preview in the editor (see config.htmlEmbed.sanitizeHtml).
 * The preview is rendered in the page of the editor, so scripts and event handlers must be removed (label profiles may be
 * edited by other users). Style elements are removed too, as they would apply to the whole page and not only to the label.
 * The label itself is generated from the unsanitized HTML, like before.
 */
export function sanitizeLabelHtml(html) {
    const sanitized = DOMPurify.sanitize(html, {FORBID_TAGS: ['style']});
    return {html: sanitized, hasChanged: sanitized !== html};
}

/**
 * The content of the dialog: A large textarea for the HTML source of the snippet
 */
class HtmlSourceView extends View {
    constructor(locale) {
        super(locale);

        this.setTemplate({
            tag: 'textarea',
            attributes: {
                class: ['ck', 'ck-reset', 'ck-input', 'ck-input-text', 'partdb-html-embed-source'],
                placeholder: locale.t('Paste raw HTML here...'),
                spellcheck: 'false',
            },
        });
    }

    get value() {
        return this.element.value;
    }

    set value(value) {
        this.element.value = value;
    }

    focus() {
        this.element.focus();
    }
}

/**
 * Shows HTML snippets (HtmlEmbed) rendered in the label preview, like they are printed, and edits their source in a dialog
 * instead of the small textarea inside the label.
 */
export default class PartDBLabelHtmlEmbed extends Plugin {
    static get requires() {
        return [Dialog];
    }

    static get pluginName() {
        return 'PartDBLabelHtmlEmbed';
    }

    init() {
        const editor = this.editor;
        if (!editor.plugins.has('HtmlEmbedEditing')) {
            return;
        }

        //The HtmlEmbed widget (and the "Insert HTML" toolbar button) start editing via the makeEditable() method of the
        //rawHtmlApi of the widget, so we replace it to open the dialog instead. The widget is converted again on every
        //change of its value, so this is done after each conversion.
        editor.editing.downcastDispatcher.on('insert:rawHtml', (evt, data, conversionApi) => {
            const viewElement = conversionApi.mapper.toViewElement(data.item);
            const api = viewElement?.getCustomProperty('rawHtmlApi');
            if (api) {
                const modelElement = data.item;
                api.makeEditable = () => this._openDialog(modelElement);
            }
        }, {priority: 'lowest'});
    }

    /**
     * Opens the dialog to edit the source of the given rawHtml model element.
     */
    _openDialog(modelElement) {
        const editor = this.editor;
        const t = editor.t;
        const dialog = editor.plugins.get(Dialog);
        const source = new HtmlSourceView(editor.locale);
        let saved = false;

        dialog.show({
            id: 'partdbHtmlEmbed',
            title: t('Edit HTML snippet'),
            className: 'partdb-html-embed-dialog',
            isModal: true,
            content: source,
            actionButtons: [
                {
                    label: t('Cancel'),
                    withText: true,
                    onExecute: () => dialog.hide(),
                },
                {
                    label: t('Save'),
                    class: 'ck-button-action',
                    withText: true,
                    onExecute: () => {
                        saved = true;
                        this._save(modelElement, source.value);
                        dialog.hide();
                    },
                },
            ],
            onShow: () => {
                source.value = modelElement.getAttribute('value') ?? '';
                source.focus();
            },
            onHide: () => {
                //A new snippet (inserted via the toolbar) which was not saved is removed again, instead of leaving an empty snippet
                if (!saved && !modelElement.getAttribute('value') && modelElement.root.rootName !== '$graveyard') {
                    editor.model.change(writer => writer.remove(modelElement));
                }
                editor.editing.view.focus();
            },
        });
    }

    /**
     * Saves the given source to the given rawHtml model element (the htmlEmbed command updates the selected snippet).
     */
    _save(modelElement, value) {
        const editor = this.editor;
        if (modelElement.root.rootName === '$graveyard' || value === (modelElement.getAttribute('value') ?? '')) {
            return;
        }

        editor.model.change(writer => {
            writer.setSelection(modelElement, 'on');
            editor.execute('htmlEmbed', value);
        });
    }
}
