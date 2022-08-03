import {Controller} from "@hotwired/stimulus";
import Darkmode from "darkmode-js/src";
import "darkmode-js"

export default class extends Controller {

    _darkmode;

    connect() {
        if (typeof window.getComputedStyle(document.body).mixBlendMode == 'undefined') {
            console.warn("The browser does not support mix blend mode. Darkmode will not work.");
            return;
        }

        try {
            const darkmode = new Darkmode();
            this._darkmode = darkmode;

            //Unhide darkmode button
            this._showWidget();

            //Set the switch according to our current darkmode state
            const toggler = document.getElementById("toggleDarkmode");
            toggler.checked = darkmode.isActivated();
        }
        catch (e)
        {
            console.error(e);
        }


    }

    _showWidget() {
        this.element.classList.remove('hidden');
    }

    toggleDarkmode() {
        this._darkmode.toggle();
    }
}