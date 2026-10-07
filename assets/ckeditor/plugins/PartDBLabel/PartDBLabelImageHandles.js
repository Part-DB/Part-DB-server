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

import {Plugin} from 'ckeditor5';
import {getImageKind, getSelectedImage} from "./labelGeometry";
import {setPartImagePlaceholderSize} from "./PartDBLabelPartImage";

/** The directions of the handles: [name, changes width, sign of the change] */
const HANDLES = [
    ['n', false, -1],
    ['e', true, 1],
    ['s', false, 1],
    ['w', true, -1],
];

/** The minimum size (in px) an image can be resized to */
const MIN_SIZE = 8;

/**
 * Adds handles on the edges of the selected image, to change its width or height (in addition to the corner handles of
 * CKEditor, which always keep the aspect ratio):
 *  - Part image placeholders change only the dragged dimension, so they can get any aspect ratio
 *  - Other images keep their aspect ratio, the other dimension is changed accordingly
 *  - Barcode placeholders get no handles, as their size is defined by the barcode size option of the label
 *
 * The handles are shown in an overlay over the editor content (and not inside the editing view), so that CKEditor does
 * not have to know about them. The size is only written to the model when the drag is finished (one undo step).
 */
export default class PartDBLabelImageHandles extends Plugin {
    static get pluginName() {
        return 'PartDBLabelImageHandles';
    }

    init() {
        this.editor.on('ready', () => this._init());
    }

    destroy() {
        if (this._onWindowResize) {
            window.removeEventListener('resize', this._onWindowResize);
        }
        this.container?.remove();
        super.destroy();
    }

    _init() {
        const editor = this.editor;
        this.main = editor.ui.view.element?.querySelector('.ck-editor__main');
        if (!this.main) {
            return;
        }

        this.container = document.createElement('div');
        this.container.className = 'partdb-image-handles';
        this.container.hidden = true;
        this.handles = {};
        for (const [direction, horizontal, sign] of HANDLES) {
            const handle = document.createElement('div');
            handle.className = 'partdb-image-handle partdb-image-handle-' + direction;
            handle.addEventListener('pointerdown', event => this._startDrag(event, handle, horizontal, sign));
            this.container.appendChild(handle);
            this.handles[direction] = handle;
        }
        this.main.appendChild(this.container);

        this.dragging = null;
        const update = () => this._update();
        this.listenTo(editor.model.document, 'change', update);
        this.listenTo(editor.editing.view, 'render', update);
        this.listenTo(editor.ui.focusTracker, 'change:isFocused', update);
        this._onWindowResize = update;
        window.addEventListener('resize', this._onWindowResize);
    }

    /**
     * Returns the selected image, if it can be resized with the handles, together with its DOM elements.
     */
    _getTarget() {
        const editor = this.editor;
        const image = getSelectedImage(editor);
        const kind = getImageKind(image);
        if (!image || kind === 'barcode') {
            return null;
        }

        const viewElement = editor.editing.mapper.toViewElement(image);
        const domElement = viewElement ? editor.editing.view.domConverter.mapViewToDom(viewElement) : null;
        const img = domElement?.tagName === 'IMG' ? domElement : domElement?.querySelector('img');
        if (!img) {
            return null;
        }

        return {image, kind, wrapper: domElement, img};
    }

    _update() {
        if (this.dragging || !this.container) {
            return;
        }
        const target = this._getTarget();
        if (!target || !this.editor.ui.focusTracker.isFocused) {
            this.container.hidden = true;
            return;
        }
        this.container.hidden = false;
        this._positionHandles(target.img.getBoundingClientRect());
    }

    /**
     * Positions the handles on the edges of the given rect (in viewport coordinates).
     */
    _positionHandles(rect) {
        const mainRect = this.main.getBoundingClientRect();
        const left = rect.left - mainRect.left - this.main.clientLeft + this.main.scrollLeft;
        const top = rect.top - mainRect.top - this.main.clientTop + this.main.scrollTop;

        const positions = {
            n: [left + rect.width / 2, top],
            e: [left + rect.width, top + rect.height / 2],
            s: [left + rect.width / 2, top + rect.height],
            w: [left, top + rect.height / 2],
        };
        for (const [direction, [x, y]] of Object.entries(positions)) {
            this.handles[direction].style.left = x + 'px';
            this.handles[direction].style.top = y + 'px';
        }
    }

    _startDrag(event, handle, horizontal, sign) {
        const target = this._getTarget();
        if (!target) {
            return;
        }
        //Keep the focus (and the selection) in the editor
        event.preventDefault();
        event.stopPropagation();

        const rect = target.img.getBoundingClientRect();
        this.dragging = {
            target, horizontal, sign,
            startX: event.clientX,
            startY: event.clientY,
            startWidth: rect.width,
            startHeight: rect.height,
            width: rect.width,
            height: rect.height,
        };

        handle.setPointerCapture(event.pointerId);
        const onMove = moveEvent => this._drag(moveEvent);
        const onEnd = () => {
            handle.removeEventListener('pointermove', onMove);
            handle.removeEventListener('pointerup', onEnd);
            handle.removeEventListener('pointercancel', onEnd);
            this._endDrag();
        };
        handle.addEventListener('pointermove', onMove);
        handle.addEventListener('pointerup', onEnd);
        handle.addEventListener('pointercancel', onEnd);
    }

    _drag(event) {
        const drag = this.dragging;
        if (!drag) {
            return;
        }

        let width = drag.startWidth;
        let height = drag.startHeight;
        if (drag.horizontal) {
            width = Math.max(MIN_SIZE, drag.startWidth + drag.sign * (event.clientX - drag.startX));
        } else {
            height = Math.max(MIN_SIZE, drag.startHeight + drag.sign * (event.clientY - drag.startY));
        }

        //Normal images keep their aspect ratio
        if (drag.target.kind !== 'part-image') {
            const ratio = drag.startHeight / drag.startWidth;
            if (drag.horizontal) {
                height = width * ratio;
            } else {
                width = height / ratio;
            }
        }

        drag.width = width;
        drag.height = height;
        this._setPreviewSize(drag.target, width, height);
        this._positionHandles(drag.target.img.getBoundingClientRect());
    }

    /**
     * Shows the new size while dragging, directly in the DOM (the model is only changed at the end of the drag).
     */
    _setPreviewSize(target, width, height) {
        target.img.style.setProperty('width', width + 'px', 'important');
        target.img.style.setProperty('height', height + 'px', 'important');
        target.img.style.setProperty('min-width', '0', 'important');
        target.img.style.setProperty('max-width', 'none', 'important');
        if (target.wrapper !== target.img) {
            target.wrapper.style.setProperty('width', width + 'px', 'important');
        }
    }

    _clearPreviewSize(target) {
        for (const property of ['width', 'height', 'min-width', 'max-width']) {
            target.img.style.removeProperty(property);
        }
        if (target.wrapper !== target.img) {
            target.wrapper.style.removeProperty('width');
        }
    }

    _endDrag() {
        const drag = this.dragging;
        this.dragging = null;
        if (!drag) {
            return;
        }

        //Remove the preview styles, so that the styles rendered by CKEditor for the new size are used
        this._clearPreviewSize(drag.target);

        if (drag.width !== drag.startWidth || drag.height !== drag.startHeight) {
            this.editor.model.change(writer => {
                if (drag.target.kind === 'part-image') {
                    setPartImagePlaceholderSize(writer, drag.target.image, drag.width, drag.height);
                } else {
                    //Like the resize handles of CKEditor
                    writer.setAttribute('resizedWidth', (Math.round(drag.width * 100) / 100) + 'px', drag.target.image);
                    writer.removeAttribute('resizedHeight', drag.target.image);
                }
            });
        }

        this._update();
    }
}
