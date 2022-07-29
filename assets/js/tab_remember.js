"use strict";

import {Tab} from "bootstrap";

/**
 * This listener keeps track of which tab is currently selected (using hash and localstorage) and will try to open
 * that tab on reload. That means that if the user changes something, he does not have to switch back to the tab
 * where he was before submit.
 */
class TabRememberHelper {
    constructor() {
        document.addEventListener("turbo:load", this.onLoad.bind(this));
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

            let parent = activeTab.parentElement.closest('.tab-pane');

            //Iterate over each parent tab and show it
            while(parent) {
                Tab.getOrCreateInstance(parent).show();

                parent = parent.parentElement.closest('.tab-pane');
            }

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