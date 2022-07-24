'use strict';

import "patternfly-bootstrap-treeview/src/css/bootstrap-treeview.css"
import "patternfly-bootstrap-treeview";

const SidebarHelper = class {
    constructor() {
        this.BASE = $("body").data("base-url");
        //If path doesn't end with slash, add it.
        if(this.BASE[this.BASE.length - 1] !== '/') {
            this.BASE = this.BASE + '/';
        }
        console.info("Base path is " + this.BASE);

        this.registerSidebarHideButton();
        this.fillTrees();
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

    /**
     * Fill the trees with the given data.
     */
    fillTrees()
    {
        let categories =  localStorage.getItem("tree_datasource_tree-categories");
        let devices =  localStorage.getItem("tree_datasource_tree-devices");
        let tools =  localStorage.getItem("tree_datasource_tree-tools");

        if(categories == null) {
            categories = "categories";
        }

        if(devices == null) {
            devices = "devices";
        }

        if(tools == null) {
            tools = "tools";
        }

        this.treeLoadDataSource("tree-categories", categories);
        this.treeLoadDataSource("tree-devices", devices);
        this.treeLoadDataSource("tree-tools", tools);

        this.trees_filled = true;

        let _this = this;

        //Register tree btns to expand all, or to switch datasource.
        $(".tree-btns").click(function (event) {
            event.preventDefault();
            $(this).parents("div.dropdown").removeClass('show');
            //$(this).closest(".dropdown-menu").removeClass('show');
            $(".dropdown-menu.show").removeClass("show");
            let mode = $(this).data("mode");
            let target = $(this).data("target");
            let text = $(this).text() + " \n<span class='caret'></span>"; //Add caret or it will be removed, when written into title

            if (mode==="collapse") {
                // @ts-ignore
                $('#' + target).treeview('collapseAll', { silent: true });
            }
            else if(mode==="expand") {
                // @ts-ignore
                $('#' + target).treeview('expandAll', { silent: true });
            } else {
                localStorage.setItem("tree_datasource_" + target, mode);
                _this.treeLoadDataSource(target, mode);
            }

            return false;
        });
    }

    /**
     * Load the given url into the tree with the given id.
     * @param target_id
     * @param datasource
     */
    treeLoadDataSource(target_id, datasource) {
        let text = $(".tree-btns[data-mode='" + datasource + "']").html();
        text = text + " \n<span class='caret'></span>"; //Add caret or it will be removed, when written into title
        switch(datasource) {
            case "categories":
                this.initTree("#" + target_id, 'tree/categories');
                break;
            case "locations":
                this.initTree("#" + target_id, 'tree/locations');
                break;
            case "footprints":
                this.initTree("#" + target_id, 'tree/footprints');
                break;
            case "manufacturers":
                this.initTree("#" + target_id, 'tree/manufacturers');
                break;
            case "suppliers":
                this.initTree("#" + target_id, 'tree/suppliers');
                break;
            case "tools":
                this.initTree("#" + target_id, 'tree/tools');
                break;
            case "devices":
                this.initTree("#" + target_id, 'tree/devices');
                break;
        }

        $( "#" + target_id + "-title").html(text);
    }

    /**
     * Fill a treeview with data from the given url.
     * @param tree The Jquery selector for the tree (e.g. "#tree-tools")
     * @param url The url from where the data should be loaded
     */
    initTree(tree, url) {
        //Get primary color from css variable
        const primary_color = getComputedStyle(document.documentElement).getPropertyValue('--bs-info');

        //let contextmenu_handler = this.onNodeContextmenu;
        $.getJSON(this.BASE + url, function (data) {
            // @ts-ignore
            $(tree).treeview({
                data: data,
                enableLinks: true,
                showIcon: false,
                showBorder: true,
                searchResultBackColor: primary_color,
                searchResultColor: '#000',
                onNodeSelected: function(event, data) {
                    if(data.href) {

                        //Simulate a click so we just change the inner frame
                        let a = document.createElement('a');
                        a.setAttribute('href', data.href);
                        a.innerHTML = "";
                        $(tree).append(a);
                        a.click();
                        a.remove();
                        //Turbo.visit(data.href)
                    }
                },
                //onNodeContextmenu: contextmenu_handler,
                expandIcon: "fas fa-plus fa-fw fa-treeview", collapseIcon: "fas fa-minus fa-fw fa-treeview"})
                .on('initialized', function() {
                    $(this).treeview('collapseAll', { silent: true });

                    //Implement searching if needed.
                    if($(this).data('treeSearch')) {
                        let _this = this;
                        let $search = $($(this).data('treeSearch'));
                        $search.on( 'input', function() {
                            $(_this).treeview('collapseAll', { silent: true });
                            $(_this).treeview('search', [$search.val()]);
                        });
                    }
                });
        });
    }
}

export default new SidebarHelper();