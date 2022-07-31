'use strict';

import "patternfly-bootstrap-treeview/src/css/bootstrap-treeview.css"
import "patternfly-bootstrap-treeview";

class SidebarHelper {
    constructor() {
        this.BASE = $("body").data("base-url");
        //If path doesn't end with slash, add it.
        if(this.BASE[this.BASE.length - 1] !== '/') {
            this.BASE = this.BASE + '/';
        }
        console.info("Base path is " + this.BASE);

        this.registerSidebarHideButton();
        //this.fillTrees();
    }

    registerSidebarHideButton()
    {
        let $sidebar = $("#fixed-sidebar");
        let $container = $("#main");
        let $toggler = $('#sidebar-toggle-button');

        function sidebarHide() {
            $sidebar.hide();
            $container.removeClass('col-md-9 col-lg-10 offset-md-3 offset-lg-2');
            $container.addClass('col-12');
            $toggler.html('<i class="fas fa-angle-right"></i>');
            $toggler.data('hidden', true);
            localStorage.setItem('sidebarHidden', 'true');
        }
        function sidebarShow() {
            let $sidebar = $("#fixed-sidebar");
            $sidebar.show();
            let $container = $("#main");
            $container.removeClass('col-12');
            $container.addClass('col-md-9 col-lg-10 offset-md-3 offset-lg-2');
            $toggler.html('<i class="fas fa-angle-left"></i>');
            $toggler.data('hidden', false);
            localStorage.setItem('sidebarHidden', 'false');
        }

        //Make the state persistent over reloads
        if(localStorage.getItem('sidebarHidden') === 'true') {
            sidebarHide();
        }

        //Register handler
        $toggler.click(function() {
            if($(this).data('hidden')) {
                sidebarShow();
            } else {
                sidebarHide();
            }
        });
    }
}

export default new SidebarHelper();