import BSTreeViewOptions from "./BSTreeViewOptions";
import BSTreeViewEventOptions from "./BSTreeViewEventOptions";
import {default as BSTreeViewNode} from "./BSTreeViewNode";
import BSTreeViewNodeState from "./BSTreeViewNodeState";
import BSTreeViewSelectOptions from "./BSTreeViewSelectOptions";
import BSTreeViewDisableOptions from "./BSTreeViewDisableOptions";
import BSTreeViewExpandOptions from "./BSTreeViewExpandOptions";
import BSTreeSearchOptions from "./BSTreeSearchOptions";


const pluginName = 'treeview';

const EVENT_LOADING = 'bs-tree:loading';
const EVENT_LOADING_FAILED = 'bs-tree:loadingFailed';
const EVENT_INITIALIZED = 'bs-tree:initialized';
const EVENT_NODE_RENDERED = 'bs-tree:nodeRendered';
const EVENT_RENDERED = 'bs-tree:rendered';
const EVENT_DESTROYED = 'bs-tree:destroyed';

const EVENT_NODE_CHECKED = 'bs-tree:nodeChecked';
const EVENT_NODE_COLLAPSED = 'bs-tree:nodeCollapsed';
const EVENT_NODE_DISABLED = 'bs-tree:nodeDisabled';
const EVENT_NODE_ENABLED = 'bs-tree:nodeEnabled';
const EVENT_NODE_EXPANDED = 'bs-tree:nodeExpanded';
const EVENT_NODE_SELECTED = 'bs-tree:nodeSelected';
const EVENT_NODE_UNCHECKED = 'bs-tree:nodeUnchecked';
const EVENT_NODE_UNSELECTED = 'bs-tree:nodeUnselected';

const EVENT_SEARCH_COMPLETED = 'bs-tree:searchCompleted';
const EVENT_SEARCH_CLEARED = 'bs-tree:searchCleared';

function templateElement(tagType: string, classes: string): HTMLElement {
    const el = document.createElement(tagType);
    if(classes.length > 0) {
        el.classList.add(...classes.split(" "));
    }
    return el;
}

export default class BSTreeView
{
    _template = {
        tree: templateElement('ul', "list-group"),
        node: templateElement("li", "list-group-item"),
        indent: templateElement("span", "indent"),
        icon: {
            node: templateElement("span", "icon node-icon"),
            expand: templateElement("span", "icon expand-icon"),
            check: templateElement("span", "icon check-icon"),
            empty:  templateElement("span", "icon")
        },
        image: templateElement("span", "image"),
        badge: templateElement("span", ""),
        text: templateElement("span", "text"),
    };

    _css = '.treeview .list-group-item{cursor:pointer}.treeview span.indent{margin-left:10px;margin-right:10px}.treeview span.icon{width:12px;margin-right:5px}.treeview .node-disabled{color:silver;cursor:not-allowed}'


    /**
     * @param {HTMLElement} The HTMLElement this tree applies to
     */
    element: HTMLElement;

    wrapper: HTMLElement|null;

    /**
     * {string}
     * @private
     */
    _elementId: string;

    _styleId: string;

    _tree: BSTreeViewNode[];

    _nodes: Map<string, BSTreeViewNode>;
    _orderedNodes: Map<string, BSTreeViewNode>;

    _checkedNodes: BSTreeViewNode[];

    /**
     * @private
     * {boolean}
     */
    _initialized: boolean;

    _options: BSTreeViewOptions;

    constructor(element: HTMLElement, options: BSTreeViewOptions|object)
    {
        this.element = element;
        this._elementId = element.id ?? "bs-treeview-" + Math.floor(Math.random() * 1000000);
        this._styleId = this._elementId + '-style';

        this._init(options);
    }

    _init (options: BSTreeViewOptions|object)
    {
        this._tree = [];
        this._initialized = false;

        //If options is a BSTreeViewOptions object, we can use it directly
        if(options instanceof BSTreeViewOptions) {
            this._options = options;
        } else {
            //Otherwise we have to apply our options object on it
            this._options = new BSTreeViewOptions(options);
        }

        // Cache empty icon DOM template
        this._template.icon.empty.classList.add(...this._options.emptyIcon.split(" "));

        this._destroy();
        this._subscribeEvents();

        this._triggerEvent(EVENT_LOADING, null, new BSTreeViewEventOptions({silent: true}));
        this._load(this._options)
            .then((data) => {
                // load done
                return this._tree = data;
            })
            .catch((error) => {
                // load fail
                this._triggerEvent(EVENT_LOADING_FAILED, error, new BSTreeViewEventOptions());
            })
            .then((treeData) => {
                // initialize data
                if(treeData) {
                    return this._setInitialStates(new BSTreeViewNode({nodes: treeData}), 0);
                }
            })
            .then(() => {
                // render to DOM
                this._render();
            })
        ;
    }

    _load (options: BSTreeViewOptions): Promise<BSTreeViewNode[]> {
        if (options.data) {
            return this._loadLocalData(options);
        } else if (options.ajaxURL) {
            return this._loadRemoteData(options);
        }
        throw new Error("No data source defined.");
    }

    _loadRemoteData (options: BSTreeViewOptions): Promise<BSTreeViewNode[]> {
        return new Promise((resolve, reject) => {
            fetch(options.ajaxURL, options.ajaxConfig).then((response) => {
                resolve(response.json());
            })
                .catch((error) => reject(error));
        });
    }

    _loadLocalData (options: BSTreeViewOptions): Promise<BSTreeViewNode[]> {
        return new Promise((resolve, reject) => {
            //if options.data is a string we need to JSON decode it first
            if (typeof options.data === 'string') {
                try {
                    resolve(JSON.parse(options.data));
                } catch (error) {
                    reject(error);
                }
            } else {
                resolve(options.data);
            }
        });
    };

    _remove () {
        this._destroy();
        //$.removeData(this, pluginName);
        const styleElement = document.getElementById(this._styleId);
        styleElement.remove();
    };

    _destroy () {
        if (!this._initialized) return;
        this._initialized = false;

        this._triggerEvent(EVENT_DESTROYED, null, new BSTreeViewEventOptions());

        // Switch off events
        this._unsubscribeEvents();

        // Tear down
        this.wrapper.remove();
        this.wrapper = null;
    };

    _unsubscribeEvents () {
        if (typeof (this._options.onLoading) === 'function') {
            this.element.removeEventListener(EVENT_LOADING, this._options.onLoading);
        }

        if (typeof (this._options.onLoadingFailed) === 'function') {
            this.element.removeEventListener(EVENT_LOADING_FAILED, this._options.onLoadingFailed);
        }

        if (typeof (this._options.onInitialized) === 'function') {
            this.element.removeEventListener(EVENT_INITIALIZED, this._options.onInitialized);
        }

        if (typeof (this._options.onNodeRendered) === 'function') {
            this.element.removeEventListener(EVENT_NODE_RENDERED, this._options.onNodeRendered);
        }

        if (typeof (this._options.onRendered) === 'function') {
            this.element.removeEventListener(EVENT_RENDERED, this._options.onRendered);
        }

        if (typeof (this._options.onDestroyed) === 'function') {
            this.element.removeEventListener(EVENT_DESTROYED, this._options.onDestroyed);
        }

        this.element.removeEventListener('click', this._clickHandler.bind(this));

        if (typeof (this._options.onNodeChecked) === 'function') {
            this.element.removeEventListener(EVENT_NODE_CHECKED, this._options.onNodeChecked);
        }

        if (typeof (this._options.onNodeCollapsed) === 'function') {
            this.element.removeEventListener(EVENT_NODE_COLLAPSED, this._options.onNodeCollapsed);
        }

        if (typeof (this._options.onNodeDisabled) === 'function') {
            this.element.removeEventListener(EVENT_NODE_DISABLED, this._options.onNodeDisabled);
        }

        if (typeof (this._options.onNodeEnabled) === 'function') {
            this.element.removeEventListener(EVENT_NODE_ENABLED, this._options.onNodeEnabled);
        }

        if (typeof (this._options.onNodeExpanded) === 'function') {
            this.element.removeEventListener(EVENT_NODE_EXPANDED, this._options.onNodeExpanded);
        }

        if (typeof (this._options.onNodeSelected) === 'function') {
            this.element.removeEventListener(EVENT_NODE_SELECTED, this._options.onNodeSelected);
        }

        if (typeof (this._options.onNodeUnchecked) === 'function') {
            this.element.removeEventListener(EVENT_NODE_UNCHECKED, this._options.onNodeUnchecked);
        }

        if (typeof (this._options.onNodeUnselected) === 'function') {
            this.element.removeEventListener(EVENT_NODE_UNSELECTED, this._options.onNodeUnselected);
        }

        if (typeof (this._options.onSearchComplete) === 'function') {
            this.element.removeEventListener(EVENT_SEARCH_COMPLETED, this._options.onSearchComplete);
        }

        if (typeof (this._options.onSearchCleared) === 'function') {
            this.element.removeEventListener(EVENT_SEARCH_CLEARED, this._options.onSearchCleared);
        }
    };

    _subscribeEvents () {
        this._unsubscribeEvents();

        if (typeof (this._options.onLoading) === 'function') {
            this.element.addEventListener(EVENT_LOADING, this._options.onLoading);
        }

        if (typeof (this._options.onLoadingFailed) === 'function') {
            this.element.addEventListener(EVENT_LOADING_FAILED, this._options.onLoadingFailed);
        }

        if (typeof (this._options.onInitialized) === 'function') {
            this.element.addEventListener(EVENT_INITIALIZED, this._options.onInitialized);
        }

        if (typeof (this._options.onNodeRendered) === 'function') {
            this.element.addEventListener(EVENT_NODE_RENDERED, this._options.onNodeRendered);
        }

        if (typeof (this._options.onRendered) === 'function') {
            this.element.addEventListener(EVENT_RENDERED, this._options.onRendered);
        }

        if (typeof (this._options.onDestroyed) === 'function') {
            this.element.addEventListener(EVENT_DESTROYED, this._options.onDestroyed);
        }

        this.element.addEventListener('click', this._clickHandler.bind(this));

        if (typeof (this._options.onNodeChecked) === 'function') {
            this.element.addEventListener(EVENT_NODE_CHECKED, this._options.onNodeChecked);
        }

        if (typeof (this._options.onNodeCollapsed) === 'function') {
            this.element.addEventListener(EVENT_NODE_COLLAPSED, this._options.onNodeCollapsed);
        }

        if (typeof (this._options.onNodeDisabled) === 'function') {
            this.element.addEventListener(EVENT_NODE_DISABLED, this._options.onNodeDisabled);
        }

        if (typeof (this._options.onNodeEnabled) === 'function') {
            this.element.addEventListener(EVENT_NODE_ENABLED, this._options.onNodeEnabled);
        }

        if (typeof (this._options.onNodeExpanded) === 'function') {
            this.element.addEventListener(EVENT_NODE_EXPANDED, this._options.onNodeExpanded);
        }

        if (typeof (this._options.onNodeSelected) === 'function') {
            this.element.addEventListener(EVENT_NODE_SELECTED, this._options.onNodeSelected);
        }

        if (typeof (this._options.onNodeUnchecked) === 'function') {
            this.element.addEventListener(EVENT_NODE_UNCHECKED, this._options.onNodeUnchecked);
        }

        if (typeof (this._options.onNodeUnselected) === 'function') {
            this.element.addEventListener(EVENT_NODE_UNSELECTED, this._options.onNodeUnselected);
        }

        if (typeof (this._options.onSearchComplete) === 'function') {
            this.element.addEventListener(EVENT_SEARCH_COMPLETED, this._options.onSearchComplete);
        }

        if (typeof (this._options.onSearchCleared) === 'function') {
            this.element.addEventListener(EVENT_SEARCH_CLEARED, this._options.onSearchCleared);
        }
    };

    _triggerEvent (eventType: string, data: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewEventOptions = null) {
        if (options && !options.silent) {
            const event = new CustomEvent(eventType, { detail: {data: data, eventOptions: options, treeView: this} });

            this.element.dispatchEvent(event);
        }
    }

    /*
        Recurse the tree structure and ensure all nodes have
        valid initial states.  User defined states will be preserved.
        For performance we also take this opportunity to
        index nodes in a flattened ordered structure
    */
    _setInitialStates (node: BSTreeViewNode, level: number): Promise<any> {
        this._nodes = new Map<string, BSTreeViewNode>();

        const promise = new Promise((resolve) => {
            this._setInitialState(node, level)
            resolve(false);
        });

        promise.then(() => {
            this._orderedNodes = this._sortNodes(this._nodes);
            this._inheritCheckboxChanges();
            this._triggerEvent(EVENT_INITIALIZED, Array.from(this._orderedNodes.values()));
        });

        return promise;
    };

    _setInitialState (node: BSTreeViewNode, level: number): void {
        if (!node.nodes) return;
        level += 1;

        let parent = node;
        node.nodes.forEach((node, index) => {
            // level : hierarchical tree level, starts at 1
            node.level = level;

            // index : relative to siblings
            node.index = index;

            // nodeId : unique, hierarchical identifier
            node.nodeId = (parent && parent.nodeId) ?
                parent.nodeId + '.' + node.index :
                (level - 1) + '.' + node.index;

            // parentId : transversing up the tree
            node.parentId = parent.nodeId;

            // if not provided set selectable default value
            if (!node.hasOwnProperty('selectable')) {
                node.selectable = true;
            }

            // if not provided set checkable default value
            if (!node.hasOwnProperty('checkable')) {
                node.checkable = true;
            }

            // where provided we should preserve states
            node.state = node.state || new BSTreeViewNodeState();

            // set checked state; unless set always false
            if (!node.state.hasOwnProperty('checked')) {
                node.state.checked = false;
            }

            // convert the undefined string if hierarchical checks are enabled
            if (this._options.hierarchicalCheck && node.state.checked === null) {
                node.state.checked = null;
            }

            // set enabled state; unless set always false
            if (!node.state.hasOwnProperty('disabled')) {
                node.state.disabled = false;
            }

            // set expanded state; if not provided based on levels
            if (!node.state.hasOwnProperty('expanded')) {
                if (!node.state.disabled &&
                    (level < this._options.levels) &&
                    (node.nodes && node.nodes.length > 0)) {
                    node.state.expanded = true;
                }
                else {
                    node.state.expanded = false;
                }
            }

            // set selected state; unless set always false
            if (!node.state.hasOwnProperty('selected')) {
                node.state.selected = false;
            }

            // set visible state; based parent state plus levels
            if ((parent && parent.state && parent.state.expanded) ||
                (level <= this._options.levels)) {
                node.state.visible = true;
            }
            else {
                node.state.visible = false;
            }

            // recurse child nodes and transverse the tree, depth-first
            if (node.nodes) {
                if (node.nodes.length > 0) {
                    this._setInitialState(node, level);
                }
                else {
                    delete node.nodes;
                }
            }

            // add / update indexed collection
            this._nodes.set(node.nodeId, node);
        })
    };

    _sortNodes (nodes: Map<string, BSTreeViewNode>): Map<string, BSTreeViewNode> {
        return new Map([...nodes].sort((pairA, pairB) => {
            //Index 0 of our pair variables contains the index of our map

            if (pairA[0] === pairB[0]) return 0;
            const a = pairA[0].split('.').map(function (level) { return parseInt(level); });
            const b = pairB[0].split('.').map(function (level) { return parseInt(level); });

            const c = Math.max(a.length, b.length);
            for (let i=0; i<c; i++) {
                if (a[i] === undefined) return -1;
                if (b[i] === undefined) return +1;
                if (a[i] - b[i] > 0) return +1;
                if (a[i] - b[i] < 0) return -1;
            }
        }));
    };

    _clickHandler (event: Event) {
        const target = event.target as HTMLElement;
        const node = this.targetNode(target);
        if (!node || node.state.disabled) return;

        const classList = target.classList;
        if (classList.contains('expand-icon')) {
            this._toggleExpanded(node);
        }
        else if (classList.contains('check-icon')) {
            if (node.checkable) {
                this._toggleChecked(node);
            }
        }
        else {
            if (node.selectable) {
                this._toggleSelected(node);
            } else {
                this._toggleExpanded(node);
            }
        }
    };

    /* Looks up the DOM for the closest parent list item to retrieve the
     * data attribute nodeid, which is used to lookup the node in the flattened structure. */
    targetNode (target: HTMLElement): BSTreeViewNode {
        const nodeElement = target.closest('li.list-group-item') as HTMLElement;
        const nodeId = nodeElement.dataset.nodeId;
        const node = this._nodes.get(nodeId);
        if (!node) {
            console.warn('Error: node does not exist');
        }
        return node;
    };

    _toggleExpanded (node: BSTreeViewNode, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()) {
        if (!node) return;

        // Lazy-load the child nodes if possible
        if (typeof(this._options.lazyLoad) === 'function' && node.lazyLoad) {
            this._lazyLoad(node);
        } else {
            this._setExpanded(node, !node.state.expanded, options);
        }
    };

    _lazyLoad (node: BSTreeViewNode) {
        if(!node.lazyLoad) return;

        // Show a different icon while loading the child nodes
        const span = node.el.querySelector('span.expand-icon');
        span.classList.remove(...this._options.expandIcon.split(' '));
        span.classList.add(...this._options.loadingIcon.split(' '));

        this._options.lazyLoad(node, (nodes) => {
            // Adding the node will expand its parent automatically
            this.addNode(nodes, node);
        });
        // Only the first expand should do a lazy-load
        node.lazyLoad = false;
    };

    _setExpanded (node: BSTreeViewNode, state: boolean, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()): void {

        // We never pass options when rendering, so the only time
        // we need to validate state is from user interaction
        if (options && state === node.state.expanded) return;

        if (state && node.nodes) {

            // Set node state
            node.state.expanded = true;

            // Set element
            if (node.el) {
                const span = node.el.querySelector('span.expand-icon');
                span.classList.remove(...this._options.expandIcon.split(" "))
                span.classList.remove(...this._options.loadingIcon.split(" "))
                span.classList.add(...this._options.collapseIcon.split(" "));
            }

            // Expand children
            if (node.nodes && options) {
                node.nodes.forEach((node) => {
                    this._setVisible(node, true, options);
                });
            }

            // Optionally trigger event
            this._triggerEvent(EVENT_NODE_EXPANDED, node, options);
        }
        else if (!state) {

            // Set node state
            node.state.expanded = false;

            // Set element
            if (node.el) {
                const span = node.el.querySelector('span.expand-icon');
                span.classList.remove(...this._options.collapseIcon.split(" "));
                span.classList.add(...this._options.expandIcon.split(" "));
            }

            // Collapse children
            if (node.nodes && options) {
                node.nodes.forEach ((node) => {
                    this._setVisible(node, false, options);
                    this._setExpanded(node, false, options);
                });
            }

            // Optionally trigger event
            this._triggerEvent(EVENT_NODE_COLLAPSED, node, options);
        }
    };

    _setVisible (node: BSTreeViewNode, state: boolean, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()): void {

        if (options && state === node.state.visible) return;

        if (state) {

            // Set node state
            node.state.visible = true;

            // Set element
            if (node.el) {
                node.el.classList.remove('node-hidden');
            }
        }
        else {

            // Set node state to unchecked
            node.state.visible = false;

            // Set element
            if (node.el) {
                node.el.classList.add('node-hidden');
            }
        }
    };

    _toggleSelected (node: BSTreeViewNode, options: BSTreeViewSelectOptions = new BSTreeViewSelectOptions()): this {
        if (!node) return;
        this._setSelected(node, !node.state.selected, options);
        return this;
    };

    _setSelected (node: BSTreeViewNode, state: boolean, options = new BSTreeViewSelectOptions()): this {

        // We never pass options when rendering, so the only time
        // we need to validate state is from user interaction
        if (options && (state === node.state.selected)) return;

        if (state) {

            // If multiSelect false, unselect previously selected
            if (!this._options.multiSelect) {
                const selectedNodes = this._findNodes('true', 'state.selected');
                selectedNodes.forEach((node) => {
                    options.unselecting = true;

                    this._setSelected(node, false, options);
                });
            }

            // Set node state
            node.state.selected = true;

            // Set element
            if (node.el) {
                node.el.classList.add('node-selected');

                if (node.selectedIcon || this._options.selectedIcon) {
                    const span = node.el.querySelector('span.node-icon');
                    span.classList.remove(...(node.icon || this._options.nodeIcon).split(" "));
                    span.classList.add(...(node.selectedIcon || this._options.selectedIcon).split(" "));
                }
            }

            // Optionally trigger event
            this._triggerEvent(EVENT_NODE_SELECTED, node, options);
        }
        else {

            // If preventUnselect true + only one remaining selection, disable unselect
            if (this._options.preventUnselect &&
                (options && !options.unselecting) &&
                (this._findNodes('true', 'state.selected').length === 1)) {
                // Fire the nodeSelected event if reselection is allowed
                if (this._options.allowReselect) {
                    this._triggerEvent(EVENT_NODE_SELECTED, node, options);
                }
                return this;
            }

            // Set node state
            node.state.selected = false;

            // Set element
            if (node.el) {
                node.el.classList.remove('node-selected');

                if (node.selectedIcon || this._options.selectedIcon) {
                    const span = node.el.querySelector('span.node-icon');
                    span.classList.remove(...(node.selectedIcon || this._options.selectedIcon).split(" "))
                    span.classList.add(...(node.icon || this._options.nodeIcon).split(" "));
                }
            }

            // Optionally trigger event
            this._triggerEvent(EVENT_NODE_UNSELECTED, node, options);
        }

        return this;
    };

    _inheritCheckboxChanges (): void {
        if (this._options.showCheckbox && this._options.highlightChanges) {
            this._checkedNodes = [];
            this._orderedNodes.forEach((node) => {
               if(node.state.checked) {
                   this._checkedNodes.push(node);
               }
            });
        }
    };

    _toggleChecked (node: BSTreeViewNode, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()): this {
        if (!node) return;

        if (this._options.hierarchicalCheck) {
            // Event propagation to the parent/child nodes
            const childOptions = new BSTreeViewEventOptions(options);
            childOptions.silent = options.silent || !this._options.propagateCheckEvent;

            let state: boolean|null;
            let currentNode = node;
            // Temporarily swap the tree state
            node.state.checked = !node.state.checked;

            currentNode = this._nodes.get(currentNode.parentId);
            // Iterate through each parent node
            while (currentNode) {

                // Calculate the state
                state = currentNode.nodes.reduce((acc, curr) => {
                    return (acc === curr.state.checked) ? acc : null;
                }, currentNode.nodes[0].state.checked);

                // Set the state
                this._setChecked(currentNode, state, childOptions);

                currentNode = this._nodes.get(currentNode.parentId);
            }

            if (node.nodes && node.nodes.length > 0) {
                // Copy the content of the array
                let child, children = node.nodes.slice();
                // Iterate through each child node
                while (children && children.length > 0) {
                    child = children.pop();

                    // Set the state
                    this._setChecked(child, node.state.checked, childOptions);

                    // Append children to the end of the list
                    if (child.nodes && child.nodes.length > 0) {
                        children = children.concat(child.nodes.slice());
                    }
                }
            }
            // Swap back the tree state
            node.state.checked = !node.state.checked;
        }

        this._setChecked(node, !node.state.checked, options);
    };

    _setChecked (node: BSTreeViewNode, state: boolean, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()) {
        // We never pass options when rendering, so the only time
        // we need to validate state is from user interaction
        if (options && state === node.state.checked) return;

        // Highlight the node if its checkbox has unsaved changes
        if (this._options.highlightChanges) {
            const nodeNotInCheckList = this._checkedNodes.indexOf(node) == -1;
            if(nodeNotInCheckList == state) {
                node.el.classList.add('node-check-changed');
            } else {
                node.el.classList.remove('node-check-changed');
            }
        }

        if (state) {

            // Set node state
            node.state.checked = true;

            // Set element
            if (node.el) {
                node.el.classList.add('node-checked');
                node.el.classList.remove('node-checked-partial');
                const span = node.el.querySelector('span.check-icon');
                span.classList.remove(...this._options.uncheckedIcon.split(" "))
                span.classList.remove(...this._options.partiallyCheckedIcon.split(" "))
                span.classList.add(...this._options.checkedIcon.split(" "));
            }

            // Optionally trigger event
            this._triggerEvent(EVENT_NODE_CHECKED, node, options);
        }
        else if (state === null && this._options.hierarchicalCheck) {

            // Set node state to partially checked
            node.state.checked = null;

            // Set element
            if (node.el) {
                node.el.classList.add('node-checked-partial');
                node.el.classList.remove('node-checked');
                const span = node.el.querySelector('span.check-icon');
                span.classList.remove(...this._options.uncheckedIcon.split(" "));
                span.classList.remove(...this._options.checkedIcon.split(" "));
                span.classList.add(...this._options.partiallyCheckedIcon.split(" "));
            }

            // Optionally trigger event, partially checked is technically unchecked
            this._triggerEvent(EVENT_NODE_UNCHECKED, node, options);
        } else {

            // Set node state to unchecked
            node.state.checked = false;

            // Set element
            if (node.el) {
                node.el.classList.remove('node-checked node-checked-partial');
                const span = node.el.querySelector('span.check-icon');
                span.classList.remove(...this._options.checkedIcon.split(" "));
                span.classList.remove(...this._options.partiallyCheckedIcon.split(" "));
                span.classList.add(...this._options.uncheckedIcon.split(" "));
            }

            // Optionally trigger event
            this._triggerEvent(EVENT_NODE_UNCHECKED, node, options);
        }
    };

    _setDisabled (node: BSTreeViewNode, state: boolean, options: BSTreeViewDisableOptions = new BSTreeViewDisableOptions()) {

        // We never pass options when rendering, so the only time
        // we need to validate state is from user interaction
        if (options && state === node.state.disabled) return;

        if (state) {

            // Set node state to disabled
            node.state.disabled = true;

            // Disable all other states
            if (options && !options.keepState) {
                this._setSelected(node, false, options);
                this._setChecked(node, false, options);
                this._setExpanded(node, false, options);
            }

            // Set element
            if (node.el) {
                node.el.classList.add('node-disabled');
            }

            // Optionally trigger event
            this._triggerEvent(EVENT_NODE_DISABLED, node, options);
        }
        else {

            // Set node state to enabled
            node.state.disabled = false;

            // Set element
            if (node.el) {
                node.el.classList.remove('node-disabled');
            }

            // Optionally trigger event
            this._triggerEvent(EVENT_NODE_DISABLED, node, options);
        }
    };

    _setSearchResult (node: BSTreeViewNode, state: boolean, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()) {
        if (options && state === node.searchResult) return;

        if (state) {

            node.searchResult = true;

            if (node.el) {
                node.el.classList.add('node-result');
            }
        }
        else {

            node.searchResult = false;

            if (node.el) {
                node.el.classList.remove('node-result');
            }
        }
    };

    _render(): void {
        if (!this._initialized) {

            // Setup first time only components
            this.wrapper = this._template.tree.cloneNode(true) as HTMLElement;
            //Empty this element
            while(this.element.firstChild) {
                this.element.removeChild(this.element.firstChild);
            }


            this.element.classList.add(...pluginName.split(" "))
            this.element.appendChild(this.wrapper);

            this._injectStyle();

            this._initialized = true;
        }

        let previousNode: BSTreeViewNode|null = null;
        this._orderedNodes.forEach((node) => {
            this._renderNode(node, previousNode);
            previousNode = node;
        });

        this._triggerEvent(EVENT_RENDERED, Array.from(this._orderedNodes.values()), new BSTreeViewEventOptions());
    };

    _renderNode(node: BSTreeViewNode, previousNode: BSTreeViewNode|null): void {
        if (!node) return;

        if (!node.el) {
            node.el = this._newNodeEl(node, previousNode);
            node.el.classList.add('node-' + this._elementId);
        }
        else {
            node.el.innerHTML = "";
        }

        // Append .classes to the node
        if(node.class) {
            node.el.classList.add(...node.class.split(" "));
        }

        // Set the #id of the node if specified
        if (node.id) {
            node.el.id = node.id;
        }

        // Append custom data- attributes to the node
        if (node.dataAttr) {
            for (const key in node.dataAttr) {
                if (node.dataAttr.hasOwnProperty(key)) {
                    node.el.setAttribute('data-' + key, node.dataAttr[key]);
                }
            }
        }

        // Set / update nodeid; it can change as a result of addNode etc.
        node.el.dataset.nodeId = node.nodeId;

        // Set the tooltip attribute if present
        if (node.tooltip) {
            node.el.title = node.tooltip;
        }

        // Add indent/spacer to mimic tree structure
        for (let i = 0; i < (node.level - 1); i++) {
            node.el.append(this._template.indent.cloneNode(true) as HTMLElement);
        }

        // Add expand / collapse or empty spacer icons
        node.el
            .append(
                node.nodes || node.lazyLoad ? this._template.icon.expand.cloneNode(true) as HTMLElement : this._template.icon.empty.cloneNode(true) as HTMLElement
            );

        // Add checkbox and node icons
        if (this._options.checkboxFirst) {
            this._addCheckbox(node);
            this._addIcon(node);
            this._addImage(node);
        } else {
            this._addIcon(node);
            this._addImage(node);
            this._addCheckbox(node);
        }

        // Add text
        if (this._options.wrapNodeText) {
            const wrapper = this._template.text.cloneNode(true) as HTMLElement;
            node.el.append(wrapper);
            wrapper.append(node.text);
        } else {
            node.el.append(node.text);
        }

        // Add tags as badges
        if (this._options.showTags && node.tags) {
            node.tags.forEach(tag => {
                const template = this._template.badge.cloneNode(true) as HTMLElement;
                template.classList.add(
                    //@ts-ignore
                    ...((typeof tag === 'object' ? tag.class : undefined)
                    || node.tagsClass
                    || this._options.tagsClass).split(" ")
                );
                template.append(
                    //@ts-ignore
                    (typeof tag === 'object' ? tag.text : undefined)
                    || tag
                );

                node.el.append(template);
            });
        }

        // Set various node states
        this._setSelected(node, node.state.selected);
        this._setChecked(node, node.state.checked);
        this._setSearchResult(node, node.searchResult);
        this._setExpanded(node, node.state.expanded);
        this._setDisabled(node, node.state.disabled);
        this._setVisible(node, node.state.visible);

        // Trigger nodeRendered event
        this._triggerEvent(EVENT_NODE_RENDERED, node, new BSTreeViewEventOptions());
    };

// Add checkable icon
    _addCheckbox (node: BSTreeViewNode): void {
        if (this._options.showCheckbox && (node.hideCheckbox === undefined || node.hideCheckbox === false)) {
            node.el
                .append(this._template.icon.check.cloneNode(true) as HTMLElement);
        }
    }

// Add node icon
    _addIcon (node: BSTreeViewNode): void {
        if (this._options.showIcon && !(this._options.showImage && node.image)) {
            const template = this._template.icon.node.cloneNode(true) as HTMLElement;
            template.classList.add(...(node.icon || this._options.nodeIcon).split(" "))

            node.el.append(template);
        }
    }

    _addImage (node: BSTreeViewNode): void {
        if (this._options.showImage && node.image) {
            const template = this._template.image.cloneNode(true) as HTMLElement;
            template.classList.add('node-image');
            template.style.backgroundImage = "url('" + node.image + "')";


            node.el
                .append(

                );
        }
    }

// Creates a new node element from template and
// ensures the template is inserted at the correct position
    _newNodeEl (node: BSTreeViewNode, previousNode: BSTreeViewNode|null): HTMLElement {
        let template = this._template.node.cloneNode(true) as HTMLElement;

        if (previousNode) {
            // typical usage, as nodes are rendered in
            // sort order we add after the previous element
            previousNode.el.after(template);
        } else {
            // we use prepend instead of append,
            // to cater for root inserts i.e. nodeId 0.0
            this.wrapper.prepend(template);
        }

        return template;
    };

// Recursively remove node elements from DOM
    _removeNodeEl (node: BSTreeViewNode): void {
        if (!node) return;

        if (node.nodes) {
            node.nodes.forEach((node) => {
                this._removeNodeEl(node);
            });
        }
        node.el.remove();
    };

// Expand node, rendering it's immediate children
    _expandNode (node: BSTreeViewNode): void {
        if (!node.nodes) return;

        node.nodes.slice(0).reverse().forEach((childNode) => {
            childNode.level = node.level + 1;
            this._renderNode(childNode, node);
        });
    };

// Add inline style into head
    _injectStyle (): void {
        if (this._options.injectStyle && !document.getElementById(this._styleId)) {
            const styleElement = document.createElement('style');
            styleElement.id = this._styleId;
            styleElement.type='text/css';
            styleElement.innerHTML = this._buildStyle();
            document.head.appendChild(styleElement);
        }
    };

// Construct trees style based on user options
    _buildStyle () {
        let style = '.node-' + this._elementId + '{';

        // Basic bootstrap style overrides
        if (this._options.color) {
            style += 'color:' + this._options.color + ';';
        }

        if (this._options.backColor) {
            style += 'background-color:' + this._options.backColor + ';';
        }

        if (!this._options.showBorder) {
            style += 'border:none;';
        }
        else if (this._options.borderColor) {
            style += 'border:1px solid ' + this._options.borderColor + ';';
        }
        style += '}';

        if (this._options.onhoverColor) {
            style += '.node-' + this._elementId + ':not(.node-disabled):hover{' +
                'background-color:' + this._options.onhoverColor + ';' +
                '}';
        }

        // Style search results
        if (this._options.highlightSearchResults && (this._options.searchResultColor || this._options.searchResultBackColor)) {

            let innerStyle = ''
            if (this._options.searchResultColor) {
                innerStyle += 'color:' + this._options.searchResultColor + ';';
            }
            if (this._options.searchResultBackColor) {
                innerStyle += 'background-color:' + this._options.searchResultBackColor + ';';
            }

            style += '.node-' + this._elementId + '.node-result{' + innerStyle + '}';
            style += '.node-' + this._elementId + '.node-result:hover{' + innerStyle + '}';
        }

        // Style selected nodes
        if (this._options.highlightSelected && (this._options.selectedColor || this._options.selectedBackColor)) {

            let innerStyle = ''
            if (this._options.selectedColor) {
                innerStyle += 'color:' + this._options.selectedColor + ';';
            }
            if (this._options.selectedBackColor) {
                innerStyle += 'background-color:' + this._options.selectedBackColor + ';';
            }

            style += '.node-' + this._elementId + '.node-selected{' + innerStyle + '}';
            style += '.node-' + this._elementId + '.node-selected:hover{' + innerStyle + '}';
        }

        // Style changed nodes
        if (this._options.highlightChanges) {
            let innerStyle = 'color: ' + this._options.changedNodeColor + ';';
            style += '.node-' + this._elementId + '.node-check-changed{' + innerStyle + '}';
        }

        // Node level style overrides
        this._orderedNodes.forEach((node) => {
            if (node.color || node.backColor) {
                let innerStyle = '';
                if (node.color) {
                    innerStyle += 'color:' + node.color + ';';
                }
                if (node.backColor) {
                    innerStyle += 'background-color:' + node.backColor + ';';
                }
                style += '.node-' + this._elementId + '[data-nodeId="' + node.nodeId + '"]{' + innerStyle + '}';
            }

            if (node.iconColor) {
                let innerStyle = 'color:' + node.iconColor + ';';
                style += '.node-' + this._elementId + '[data-nodeId="' + node.nodeId + '"] .node-icon{' + innerStyle + '}';
            }
        });

        return this._css + style;
    };

    /**
     Returns an array of matching node objects.
     @param {String} pattern - A pattern to match against a given field
     @return {String} field - Field to query pattern against
     */
    findNodes (pattern: string, field: string): BSTreeViewNode[] {
        return this._findNodes(pattern, field);
    };


    /**
     Returns an ordered aarray of node objects.
     @return {Array} nodes - An array of all nodes
     */
    getNodes (): Map<string, BSTreeViewNode> {
        return this._orderedNodes;
    };

    /**
     Returns parent nodes for given nodes, if valid otherwise returns undefined.
     @param {Array} nodes - An array of nodes
     @returns {Array} nodes - An array of parent nodes
     */
    getParents (nodes: BSTreeViewNode[]|BSTreeViewNode): BSTreeViewNode[] {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        let parentNodes = [];
        nodes.forEach((node) => {
            const parentNode = node.parentId ? this._nodes.get(node.parentId) : false;
            if (parentNode) {
                parentNodes.push(parentNode);
            }
        });
        return parentNodes;
    };

    /**
     Returns an array of sibling nodes for given nodes, if valid otherwise returns undefined.
     @param {Array} nodes - An array of nodes
     @returns {Array} nodes - An array of sibling nodes
     */
    getSiblings (nodes: BSTreeViewNode[]|BSTreeViewNode): BSTreeViewNode[] {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        let siblingNodes = [];
        nodes.forEach((node) => {
            let parent = this.getParents([node]);
            let nodes = parent[0] ? parent[0].nodes : this._tree;
            siblingNodes = nodes.filter(function (obj) {
                return obj.nodeId !== node.nodeId;
            });
        });

        // flatten possible nested array before returning
        return siblingNodes.map((obj) => {
            return obj;
        });
    };

    /**
     Returns an array of selected nodes.
     @returns {Array} nodes - Selected nodes
     */
    getSelected (): BSTreeViewNode[] {
        return this._findNodes('true', 'state.selected');
    };

    /**
     Returns an array of unselected nodes.
     @returns {Array} nodes - Unselected nodes
     */
    getUnselected (): BSTreeViewNode[] {
        return this._findNodes('false', 'state.selected');
    };

    /**
     Returns an array of expanded nodes.
     @returns {Array} nodes - Expanded nodes
     */
    getExpanded (): BSTreeViewNode[] {
        return this._findNodes('true', 'state.expanded');
    };

    /**
     Returns an array of collapsed nodes.
     @returns {Array} nodes - Collapsed nodes
     */
    getCollapsed (): BSTreeViewNode[] {
        return this._findNodes('false', 'state.expanded');
    };

    /**
     Returns an array of checked nodes.
     @returns {Array} nodes - Checked nodes
     */
    getChecked (): BSTreeViewNode[] {
        return this._findNodes('true', 'state.checked');
    };

    /**
     Returns an array of unchecked nodes.
     @returns {Array} nodes - Unchecked nodes
     */
    getUnchecked (): BSTreeViewNode[] {
        return this._findNodes('false', 'state.checked');
    };

    /**
     Returns an array of disabled nodes.
     @returns {Array} nodes - Disabled nodes
     */
    getDisabled (): BSTreeViewNode[] {
        return this._findNodes('true', 'state.disabled');
    };

    /**
     Returns an array of enabled nodes.
     @returns {Array} nodes - Enabled nodes
     */
    getEnabled (): BSTreeViewNode[] {
        return this._findNodes('false', 'state.disabled');
    };


    /**
     Add nodes to the tree.
     @param {Array} nodes  - An array of nodes to add
     @param {optional Object} parentNode  - The node to which nodes will be added as children
     @param {optional number} index  - Zero based insert index
     @param {optional Object} options
     */
    addNode (nodes: BSTreeViewNode[]|BSTreeViewNode, parentNode: BSTreeViewNode|null = null, index: number = null, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()) {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        if (parentNode instanceof Array) {
            parentNode = parentNode[0];
        }

        // identify target nodes; either the tree's root or a parent's child nodes
        let targetNodes;
        if (parentNode && parentNode.nodes) {
            targetNodes = parentNode.nodes;
        } else if (parentNode) {
            targetNodes = parentNode.nodes = [];
        } else {
            targetNodes = this._tree;
        }

        // inserting nodes at specified positions
        nodes.forEach((node, i) => {
            let insertIndex = (typeof(index) === 'number') ? (index + i) : (targetNodes.length + 1);
            targetNodes.splice(insertIndex, 0, node);
        });

        // initialize new state and render changes
        this._setInitialStates(new BSTreeViewNode({nodes: this._tree}), 0)
            .then(() =>{
                if (parentNode && !parentNode.state.expanded) {
                    this._setExpanded(parentNode, true, options);
                }
                this._render();
            });
    }

    /**
     Add nodes to the tree after given node.
     @param {Array} nodes  - An array of nodes to add
     @param {Object} node  - The node to which nodes will be added after
     @param {optional Object} options
     */
    addNodeAfter (nodes: BSTreeViewNode[]|BSTreeViewNode, node: BSTreeViewNode, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()) {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        if (node instanceof Array) {
            node = node[0];
        }

        this.addNode(nodes, this.getParents(node)[0], (node.index + 1), options);
    }

    /**
     Add nodes to the tree before given node.
     @param {Array} nodes  - An array of nodes to add
     @param {Object} node  - The node to which nodes will be added before
     @param {optional Object} options
     */
    addNodeBefore (nodes: BSTreeViewNode[]|BSTreeViewNode, node: BSTreeViewNode, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()) {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        if (node instanceof Array) {
            node = node[0];
        }

        this.addNode(nodes, this.getParents(node)[0], node.index, options);
    }

    /**
     Removes given nodes from the tree.
     @param {Array} nodes  - An array of nodes to remove
     @param {optional Object} options
     */
    removeNode (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()) {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        let targetNodes: BSTreeViewNode[];
        let parentNode: BSTreeViewNode;
        nodes.forEach((node) => {

            // remove nodes from tree
            parentNode = this._nodes.get(node.parentId);
            if (parentNode) {
                targetNodes = parentNode.nodes;
            } else {
                targetNodes = this._tree;
            }
            targetNodes.splice(node.index, 1);

            // remove node from DOM
            this._removeNodeEl(node);
        });

        // initialize new state and render changes
        this._setInitialStates(new BSTreeViewNode({nodes: this._tree}), 0)
            .then(this._render.bind(this));
    };

    /**
     Updates / replaces a given tree node
     @param {Object} node  - A single node to be replaced
     @param {Object} newNode  - THe replacement node
     @param {optional Object} options
     */
    updateNode (node: BSTreeViewNode, newNode: BSTreeViewNode, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()) {
        if (node instanceof Array) {
            node = node[0];
        }

        // insert new node
        let targetNodes;
        const parentNode = this._nodes.get(node.parentId);
        if (parentNode) {
            targetNodes = parentNode.nodes;
        } else {
            targetNodes = this._tree;
        }
        targetNodes.splice(node.index, 1, newNode);

        // remove old node from DOM
        this._removeNodeEl(node);

        // initialize new state and render changes
        this._setInitialStates(new BSTreeViewNode({nodes: this._tree}), 0)
            .then(this._render.bind(this));
    };


    /**
     Selects given tree nodes
     @param {Array} nodes - An array of nodes
     @param {optional Object} options
     */
    selectNode (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewSelectOptions = new BSTreeViewSelectOptions()) {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        nodes.forEach((node) => {
            this._setSelected(node, true, options);
        });
    };

    /**
     Unselects given tree nodes
     @param {Array} nodes - An array of nodes
     @param {optional Object} options
     */
    unselectNode (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewSelectOptions) {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }


        nodes.forEach((node) => {
            this._setSelected(node, false, options);
        });
    };

    /**
     Toggles a node selected state; selecting if unselected, unselecting if selected.
     @param {Array} nodes - An array of nodes
     @param {optional Object} options
     */
    toggleNodeSelected (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewSelectOptions = new BSTreeViewSelectOptions()) {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        nodes.forEach((node) => {
            this._toggleSelected(node, options);
        }, this);
    };


    /**
     Collapse all tree nodes
     @param {optional Object} options
     */
    collapseAll (options: BSTreeViewExpandOptions = new BSTreeViewExpandOptions()) {
        options.levels = options.levels || 999;
        this.collapseNode(this._tree, options);
    };

    /**
     Collapse a given tree node
     @param {Array} nodes - An array of nodes
     @param {optional Object} options
     */
    collapseNode (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewExpandOptions = new BSTreeViewExpandOptions()): void {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        nodes.forEach((node) => {
            this._setExpanded(node, false, options);
        });
    };

    /**
     Expand all tree nodes
     @param {optional Object} options
     */
    expandAll (options: BSTreeViewExpandOptions = new BSTreeViewExpandOptions()): void {
        options.levels = options.levels || 999;
        this.expandNode(this._tree, options);
    };

    /**
     Expand given tree nodes
     @param {Array} nodes - An array of nodes
     @param {optional Object} options
     */
    expandNode (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewExpandOptions = new BSTreeViewExpandOptions()) {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        nodes.forEach((node) => {
            // Do not re-expand already expanded nodes
            if (node.state.expanded) return;

            if (typeof(this._options.lazyLoad) === 'function' && node.lazyLoad) {
                this._lazyLoad(node);
            }

            this._setExpanded(node, true, options);
            if (node.nodes) {
                this._expandLevels(node.nodes, options.levels-1, options);
            }
        });
    };

    _expandLevels (nodes: BSTreeViewNode[]|BSTreeViewNode, level: number, options: BSTreeViewExpandOptions = new BSTreeViewExpandOptions()): void {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        nodes.forEach((node) => {
            this._setExpanded(node, (level > 0), options);
            if (node.nodes) {
                this._expandLevels(node.nodes, level-1, options);
            }
        });
    };

    /**
     Reveals given tree nodes, expanding the tree from node to root.
     @param {Array} nodes - An array of nodes
     @param {optional Object} options
     */
    revealNode (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewExpandOptions = new BSTreeViewExpandOptions()): void {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        nodes.forEach((node) => {
            let parentNode = node;
            let tmpNode;
            while (tmpNode = this.getParents([parentNode])[0]) {
                parentNode = tmpNode;
                this._setExpanded(parentNode, true, options);
            }
        });
    };

    /**
     Toggles a node's expanded state; collapsing if expanded, expanding if collapsed.
     @param {Array} nodes - An array of nodes
     @param {optional Object} options
     */
    toggleNodeExpanded (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewExpandOptions = new BSTreeViewExpandOptions()): void {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        nodes.forEach((node) => {
            this._toggleExpanded(node, options);
        });

    };


    /**
     Check all tree nodes
     @param {optional Object} options
     */
    checkAll (options: BSTreeViewEventOptions = new BSTreeViewEventOptions()): void {
        this._orderedNodes.forEach((node) => {
            if(!node.state.checked) {
                this._setChecked(node, true, options);
            }
        });
    };

    /**
     Checks given tree nodes
     @param {Array} nodes - An array of nodes
     @param {optional Object} options
     */
    checkNode (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()): void {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        nodes.forEach((node) => {
            this._setChecked(node, true, options);
        });
    };

    /**
     Uncheck all tree nodes
     @param {optional Object} options
     */
    uncheckAll (options: BSTreeViewEventOptions = new BSTreeViewEventOptions()): void {
        this._orderedNodes.forEach((node) => {
            if(node.state.checked || node.state.checked === undefined) {
                this._setChecked(node, false, options);
            }
        });
    };

    /**
     Uncheck given tree nodes
     @param {Array} nodes - An array of nodes
     @param {optional Object} options
     */
    uncheckNode (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()): void {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        nodes.forEach((node) => {
            this._setChecked(node, false, options);
        });
    };

    /**
     Toggles a node's checked state; checking if unchecked, unchecking if checked.
     @param {Array} nodes - An array of nodes
     @param {optional Object} options
     */
    toggleNodeChecked (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewEventOptions = new BSTreeViewEventOptions()): void {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        nodes.forEach((node) => {
            this._toggleChecked(node, options);
        });
    };

    /**
     Saves the current state of checkboxes as default, cleaning up any highlighted changes
     */
    unmarkCheckboxChanges (): void {
        this._inheritCheckboxChanges();

        this._nodes.forEach((node) => {
           node.el.classList.remove('node-check-changed');
        });
    };

    /**
     Disable all tree nodes
     @param {optional Object} options
     */
    disableAll (options: BSTreeViewDisableOptions = new BSTreeViewDisableOptions()): void {
        const nodes = this._findNodes('false', 'state.disabled');
        nodes.forEach((node) => {
           this._setDisabled(node,true, options);
        });
    };

    /**
     Disable given tree nodes
     @param {Array} nodes - An array of nodes
     @param {optional Object} options
     */
    disableNode (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewDisableOptions = new BSTreeViewDisableOptions()): void {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        nodes.forEach((node) => {
            this._setDisabled(node, true, options);
        });
    };

    /**
     Enable all tree nodes
     @param {optional Object} options
     */
    enableAll (options: BSTreeViewDisableOptions = new BSTreeViewDisableOptions()): void {
        const nodes = this._findNodes('true', 'state.disabled');

        nodes.forEach((node) => {
            this._setDisabled(node, false, options);
        });
    };

    /**
     Enable given tree nodes
     @param {Array} nodes - An array of nodes
     @param {optional Object} options
     */
    enableNode (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewDisableOptions = new BSTreeViewDisableOptions()) {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        nodes.forEach((node) => {
            this._setDisabled(node, false, options);
        })
    };

    /**
     Toggles a node's disabled state; disabling is enabled, enabling if disabled.
     @param {Array} nodes - An array of nodes
     @param {optional Object} options
     */
    toggleNodeDisabled (nodes: BSTreeViewNode[]|BSTreeViewNode, options: BSTreeViewDisableOptions = new BSTreeViewDisableOptions()): void {
        if (!(nodes instanceof Array)) {
            nodes = [nodes];
        }

        nodes.forEach((node) => {
            this._setDisabled(node, !node.state.disabled, options);
        })
    };


    /**
     Searches the tree for nodes (text) that match given criteria
     @param {String} pattern - A given string to match against
     @param {optional Object} options - Search criteria options
     @return {Array} nodes - Matching nodes
     */
    search (pattern: string, options: BSTreeSearchOptions = new BSTreeSearchOptions()): BSTreeViewNode[] {
        let previous = this._getSearchResults();
        let results = [];

        if (pattern && pattern.length > 0) {

            if (options.exactMatch) {
                pattern = '^' + pattern + '$';
            }

            let modifier = 'g';
            if (options.ignoreCase) {
                modifier += 'i';
            }

            results = this._findNodes(pattern, 'text', modifier);
        }

        // Clear previous results no longer matched
        this._diffArray<BSTreeViewNode>(results, previous).forEach((node) => {
            this._setSearchResult(node, false, options);
        });

        // Set new results
        this._diffArray<BSTreeViewNode>(previous, results).forEach((node) => {
            this._setSearchResult(node, true, options);
        });

        // Reveal hidden nodes
        if (results && options.revealResults) {
            this.revealNode(results);
        }

        this._triggerEvent(EVENT_SEARCH_COMPLETED, results, options);

        return results;
    };

    /**
     Clears previous search results
     */
    clearSearch (options: BSTreeSearchOptions = new BSTreeSearchOptions()): void {
        const results = this._getSearchResults();
        results.forEach((node) => {
            this._setSearchResult(node, false, options);
        });

        this._triggerEvent(EVENT_SEARCH_CLEARED, results, options);
    };

    _getSearchResults (): BSTreeViewNode[] {
        return this._findNodes('true', 'searchResult');
    };

    _diffArray<T> (a: Array<T>, b: Array<T>) {
        let diff: Array<T> = [];

        b.forEach((n) => {
           if (a.indexOf(n) === -1) {
               diff.push(n);
           }
        });
        return diff;
    };

    /**
     Find nodes that match a given criteria
     @param {String} pattern - A given string to match against
     @param {optional String} attribute - Attribute to compare pattern against
     @param {optional String} modifier - Valid RegEx modifiers
     @return {Array} nodes - Nodes that match your criteria
     */
    _findNodes (pattern: string, attribute: string = 'text', modifier: string = 'g'): BSTreeViewNode[] {

        let tmp = [];

        this._orderedNodes.forEach((node) => {
            const val = this._getNodeValue(node, attribute);
            if (typeof val === 'string') {
                if(val.match(new RegExp(pattern, modifier))) {
                    tmp.push(node);
                }
            }
        });

        return tmp;
    };

    /**
     Recursive find for retrieving nested attributes values
     All values are return as strings, unless invalid
     @param {Object} obj - Typically a node, could be any object
     @param {String} attr - Identifies an object property using dot notation
     @return {String} value - Matching attributes string representation
     */
    _getNodeValue (obj: object, attr: string): string {
        const index = attr.indexOf('.');
        if (index > 0) {
            const _obj = obj[attr.substring(0, index)];
            const _attr = attr.substring(index + 1, attr.length);
            return this._getNodeValue(_obj, _attr);
        }
        else {
            if (obj.hasOwnProperty(attr) && obj[attr] !== undefined) {
                return obj[attr].toString();
            }
            else {
                return undefined;
            }
        }
    };

}