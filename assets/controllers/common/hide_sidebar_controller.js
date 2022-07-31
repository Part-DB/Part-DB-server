import {Controller} from "@hotwired/stimulus";

const STORAGE_KEY = 'hide_sidebar';

export default class extends Controller {
    /**
     * The element representing the sidebar which can be hidden.
     * @type {HTMLElement}
     * @private
     */
    _sidebar;

    /**
     * The element of the container which is expanded to the full width.
     * @type {HTMLElement}
     * @private
     */
    _container;

    /**
     * The button which toggles the sidebar.
     * @private
     */
    _toggle_button;

    _hidden = false;

    connect() {
        this._sidebar = document.getElementById('fixed-sidebar');
        this._container = document.getElementById('main');
        this._toggle_button = this.element;

        //Make the state persistent over reloads
        if(localStorage.getItem(STORAGE_KEY) === 'true') {
            sidebarHide();
        }
    }

    hideSidebar() {
        this._sidebar.classList.add('d-none');

        this._container.classList.remove(...['col-md-9', 'col-lg-10', 'offset-md-3', 'offset-lg-2']);
        this._container.classList.add('col-12');

        //Change button icon
        this._toggle_button.innerHTML = '<i class="fas fa-angle-right"></i>';

        localStorage.setItem(STORAGE_KEY, 'true');
        this._hidden = true;
    }

    showSidebar() {
        this._sidebar.classList.remove('d-none');

        this._container.classList.remove('col-12');
        this._container.classList.add(...['col-md-9', 'col-lg-10', 'offset-md-3', 'offset-lg-2']);


        //Change button icon
        this._toggle_button.innerHTML = '<i class="fas fa-angle-left"></i>';

        localStorage.setItem(STORAGE_KEY, 'false');
        this._hidden = false;
    }

    toggleSidebar() {
        if(this._hidden) {
            this.showSidebar();
        } else {
            this.hideSidebar();
        }
    }
}