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

import { Plugin } from 'ckeditor5';
import {MarkdownGfmDataProcessor} from '@ckeditor/ckeditor5-markdown-gfm';

const ALLOWED_TAGS = [
	//Common elements
	'sup',
	'sub',
	'u',
	'kbd',
	'mark',
	'ins',
	'small',
	'abbr',
	'br',

	//Block elements
	'span',
	'p',
	'img',



	//These commands are somehow ignored: TODO
	'left',
	'center',
	'right',
];

/**
 * The GitHub Flavored Markdown (GFM) plugin with added HTML tags, which are kept in the output. (inline mode)
 *
 */
export default class ExtendedMarkdown extends Plugin {

	/**
	 * @inheritDoc
	 */
	constructor( editor ) {
		super( editor );

		editor.data.processor = new MarkdownGfmDataProcessor( editor.data.viewDocument );
		for (const tag of ALLOWED_TAGS) {
			editor.data.processor.keepHtml(tag);
		}
	}

	/**
	 * @inheritDoc
	 */
	static get pluginName() {
		return 'Markdown';
	}
}
