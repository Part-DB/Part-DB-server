import {Controller} from "@hotwired/stimulus";

import {BSTreeView, BSTreeViewNode, BS5Theme, FAIconTheme, EVENT_INITIALIZED} from "@jbtronics/bs-treeview";
import "@jbtronics/bs-treeview/styles/bs-treeview.css";

export default class extends Controller {
    static targets = [ "tree" ];

    /** @type {string} */
    _url = null;
    /** @type {BSTreeViewNode[]} */
    _data = null;

    /** @type {boolean} */
    _showTags = false;

    /**
     * @type {BSTreeView}
     * @private
     */
    _tree = null;

    connect() {
        const treeElement = this.treeTarget;
        if (!treeElement) {
            console.error("You need to define a tree target for the controller!");
            return;
        }

        this._url = this.element.dataset.treeUrl;
        this._data = this.element.dataset.treeData;

        if(this.element.dataset.treeShowTags === "true") {
            this._showTags = true;
        }

        this.reinitTree();
    }

    reinitTree()
    {
        //Fetch data and initialize tree
        this._getData()
            .then(this._fillTree.bind(this))
            .catch((err) => {
                console.error("Could not load the tree data: " + err);
            });
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
        if(this._tree) {
            this._tree.remove();
        }

        this._tree = new BSTreeView(this.treeTarget, {
            levels: 1,
            showTags: this._showTags,
            data: data,
            showIcon: false,
            onNodeSelected: function (event) {
                const node = event.detail.node;
                if (node.href) {

                    //Simulate a click so we just change the inner frame
                    let a = document.createElement('a');
                    a.setAttribute('href', node.href);
                    a.innerHTML = "";
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                }
            },
            //onNodeContextmenu: contextmenu_handler,
        }, [BS5Theme, FAIconTheme]);

        this.treeTarget.addEventListener(EVENT_INITIALIZED, (event) => {
            const treeView = event.detail.treeView;
            treeView.revealNode(treeView.getSelected());
        });


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
        this._tree.search(data);

        //Rereveal the selected node again
        this._tree.revealNode(this._tree.getSelected());
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