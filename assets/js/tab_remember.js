"use strict";

import {Tab} from "bootstrap";
import tab from "bootstrap/js/src/tab";

/**
 * This listener keeps track of which tab is currently selected (using hash and localstorage) and will try to open
 * that tab on reload. That means that if the user changes something, he does not have to switch back to the tab
 * where he was before submit.
 */
class TabRememberHelper {
    constructor() {
        document.addEventListener("turbo:load", this.onLoad.bind(this));

        //Capture is important here, as invalid events normally does not bubble
        document.addEventListener("invalid", this.onInvalid.bind(this), {capture: true});
    }

    /**
     * This functions is called when the browser side input validation fails on an input, jump to the tab to show this up
     * @param event
     */
    onInvalid(event) {
        this.revealElementOnTab(event.target);
    }

    revealElementOnTab(element) {
        let parent = element.closest('.tab-pane');

        //Iterate over each parent tab and show it
        while(parent) {
            //Invoker can either be a button or a element
            let tabInvoker = document.querySelector("button[data-content='#" + parent.id + "']")
                ?? document.querySelector("a[href='#" + parent.id + "']");
            Tab.getOrCreateInstance(tabInvoker).show();

            parent = parent.parentElement.closest('.tab-pane');
        }
    }

    onLoad(event) {
        //Determine which tab should be shown (use hash if specified, otherwise use localstorage)
        let activeTab = null;
        if (location.hash) {
            activeTab = document.querySelector('[href=\'' + location.hash + '\']');
        } else if (localStorage.getItem('activeTab')) {
            activeTab = document.querySelector('[href="' + localStorage.getItem('activeTab') + '"]');
        }

        if (activeTab) {

            //Reveal our tab selector (needed for nested tabs)
            this.revealElementOnTab(element);

            //Finally show the active tab itself
            Tab.getOrCreateInstance(activeTab).show();
        }

        //Register listener for tab change
        document.addEventListener('shown.bs.tab', this.onTabChange.bind(this));
    }

    onTabChange(event) {
        const tab = event.target;

        let tab_name = tab.getAttribute('href')
        if (history.replaceState) {
            history.replaceState(null, null, tab_name)
        } else {
            location.hash = tab_name
        }
        localStorage.setItem('activeTab', tab_name)
    }

}

export default new TabRememberHelper();