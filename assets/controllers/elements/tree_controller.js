import {Controller} from "@hotwired/stimulus";

import "../../js/lib/bootstrap-treeview/src/css/bootstrap-treeview.css"
//import "../../js/lib/bootstrap-treeview/src/js/bootstrap-treeview.js"

import {BSTreeView} from "@jbtronics/bs-treeview";

export default class extends Controller {
    static targets = [ "tree" ];

    _url = null;
    _data = null;

    _tree = null;

    connect() {
        const treeElement = this.treeTarget;
        if (!treeElement) {
            console.error("You need to define a tree target for the controller!");
            return;
        }

        this._url = this.element.dataset.treeUrl;
        this._data = this.element.dataset.treeData;

        this.reinitTree();
    }

    reinitTree()
    {
        //Fetch data and initialize tree
        this._getData()
            .then(this._fillTree.bind(this))
            /*.catch((err) => {
                console.error("Could not load the tree data: " + err);
            });*/
    }

    setData(data) {
        this._data = data;
        this.reinitTree();
    }

    setURL(url) {
        this._url = url;
        this.reinitTree();
    }

    _fillTree(data) {
        //Get primary color from css variable
        const primary_color = getComputedStyle(document.documentElement).getPropertyValue('--bs-warning');

        this._tree = new BSTreeView(this.treeTarget, {
            data: data,
            enableLinks: true,
            showIcon: false,
            showBorder: true,
            searchResultBackColor: primary_color,
            searchResultColor: '#000',
            onNodeSelected: function (event) {
                const data = event.detail.data;
                if (data.href) {

                    //Simulate a click so we just change the inner frame
                    let a = document.createElement('a');
                    a.setAttribute('href', data.href);
                    a.innerHTML = "";
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                }
            },
            //onNodeContextmenu: contextmenu_handler,
            expandIcon: "fas fa-plus fa-fw fa-treeview",
            collapseIcon: "fas fa-minus fa-fw fa-treeview"
        })
            /*.on('initialized', function () {
                //Collapse all nodes after init
                $(this).treeview('collapseAll', {silent: true});

                //Reveal the selected ones
                $(this).treeview('revealNode', [$(this).treeview('getSelected')]);
            });*/
    }

    collapseAll() {
        this._tree.collapseAll({silent: true});
    }

    expandAll() {
        this._tree.expandAll({silent: true});
    }

    searchInput(event) {
        const data = event.target.value;
        //Do nothing if no data was passed

        const tree = this.treeTarget;
        this._tree.collapseAll({silent: true});
        this._tree.search([data]);
    }

    /**
     * Check if the tree is already initialized (meaning bootstrap treeview was called on the object)
     * @private
     */
    _isInitialized() {
       return this._tree !== null;
    }

    _getData() {
        //Use lambda function to preserve this context
        return new Promise((myResolve, myReject) => {
            //If a url is defined, fetch the data from the url
            if (this._url) {
                return fetch(this._url)
                    .then((response) => myResolve(response.json()))
                    .catch((err) => myReject(err));
            }

            //Otherwise load the data provided via the data attribute
            return myResolve(this._data);
        });
    }
}