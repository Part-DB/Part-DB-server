import {Controller} from "@hotwired/stimulus";

import "patternfly-bootstrap-treeview/src/css/bootstrap-treeview.css"
import "patternfly-bootstrap-treeview";

export default class extends Controller {
    static targets = [ "tree" ];

    connect() {
        const treeElement = this.treeTarget;
        if (!treeElement) {
            console.error("You need to define a tree target for the controller!");
            return;
        }

        //Fetch data and initialize tree
        this._getData().then(this._fillTree.bind(this));

    }

    _fillTree(data) {
        //Get primary color from css variable
        const primary_color = getComputedStyle(document.documentElement).getPropertyValue('--bs-warning');

        const tree = this.treeTarget;

        $(tree).treeview({
            data: data,
            enableLinks: true,
            showIcon: false,
            showBorder: true,
            searchResultBackColor: primary_color,
            searchResultColor: '#000',
            onNodeSelected: function (event, data) {
                if (data.href) {

                    //Simulate a click so we just change the inner frame
                    let a = document.createElement('a');
                    a.setAttribute('href', data.href);
                    a.innerHTML = "";
                    $(tree).append(a);
                    a.click();
                    a.remove();
                }
            },
            //onNodeContextmenu: contextmenu_handler,
            expandIcon: "fas fa-plus fa-fw fa-treeview",
            collapseIcon: "fas fa-minus fa-fw fa-treeview"
        })
            .on('initialized', function () {
                //Collapse all nodes after init
                $(this).treeview('collapseAll', {silent: true});

                //Reveal the selected ones
                $(this).treeview('revealNode', [$(this).treeview('getSelected')]);
            });
    }

    collapseAll() {
        $(this.treeTarget).treeview('collapseAll', {silent: true});
    }

    expandAll() {
        $(this.treeTarget).treeview('expandAll', {silent: true});
    }

    searchInput(event) {
        const data = event.data;
        //Do nothing if no data was passed

        const tree = this.treeTarget;
        $(tree).treeview('collapseAll', {silent: true});
        $(tree).treeview('search', [data]);
    }

    _getData() {
        //Use lambda function to preserve this context
        return new Promise((myResolve, myReject) => {
            return myResolve(this.element.dataset.treeData);
        });
    }
}