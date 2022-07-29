"use strict";

/**
 * This listener keeps track of which tab is currently selected (using hash and localstorage) and will try to open
 * that tab on reload. That means that if the user changes something, he does not have to switch back to the tab
 * where he was before submit.
 */
class TabRememberHelper {
    constructor() {
        document.addEventListener("turbo:load", this.onLoad);
    }


    onLoad(event) {
        //Determine which tab should be shown (use hash if specified, otherwise use localstorage)
        let $activeTab = null;
        if (location.hash) {
            $activeTab = $('a[href=\'' + location.hash + '\']');
        } else if (localStorage.getItem('activeTab')) {
            $activeTab = $('a[href="' + localStorage.getItem('activeTab') + '"]');
        }

        if ($activeTab) {
            //Findout if the tab has any parent tab we have to show before
            var parents = $($activeTab).parents('.tab-pane');
            parents.each(function (n) {
                $('a[href="#' + $(this).attr('id') + '"]').tab('show');
            });
            //Finally show the active tab itself
            $activeTab.tab('show');
        }

        $('body').on('click', 'a[data-bs-toggle=\'tab\']', function (e) {
            e.preventDefault()
            var tab_name = this.getAttribute('href')
            if (history.replaceState) {
                history.replaceState(null, null, tab_name)
            } else {
                location.hash = tab_name
            }
            localStorage.setItem('activeTab', tab_name)

            $(this).tab('show');
            return false;
        });
    }

}

export default new TabRememberHelper();