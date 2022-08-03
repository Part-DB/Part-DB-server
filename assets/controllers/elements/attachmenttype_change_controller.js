import {Controller} from "@hotwired/stimulus";

/**
 * This controller synchronizes the filetype filters of the file input type with our selected attachment type
 */
export default class extends Controller
{
    _selectInput;
    _fileInput;

    connect() {
        //Find the select input for our attachment form
        this._selectInput = this.element.querySelector('select');
        //Find the file input for our attachment form
        this._fileInput = this.element.querySelector('input[type="file"]');

        this._selectInput.addEventListener('change', this.updateAllowedFiletypes.bind(this));

        //Update file file on load
        this.updateAllowedFiletypes();
    }

    updateAllowedFiletypes() {
        let selected_option = this._selectInput.options[this._selectInput.selectedIndex];
        let filetype_filter = selected_option.dataset.filetype_filter;
        //Apply filetype filter to file input
        this._fileInput.setAttribute('accept', filetype_filter);
    }
}