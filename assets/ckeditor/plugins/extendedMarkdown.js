import { Plugin } from 'ckeditor5/src/core';
import GFMDataProcessor from '@ckeditor/ckeditor5-markdown-gfm/src/gfmdataprocessor';

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

		editor.data.processor = new GFMDataProcessor( editor.data.viewDocument );
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
