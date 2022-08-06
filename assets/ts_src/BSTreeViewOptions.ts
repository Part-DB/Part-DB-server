import BSTreeViewNode from "./BSTreeViewNode";

export default class BSTreeViewOptions {
    injectStyle: boolean = true;

    levels: number = 2;

    data: BSTreeViewNode[]|string = null;
    ajaxURL: string = null;
    ajaxConfig: RequestInit = {
        method: "GET",
    };

    expandIcon: string = 'glyphicon glyphicon-plus';
    collapseIcon: string = 'glyphicon glyphicon-minus';
    loadingIcon: string = 'glyphicon glyphicon-hourglass';
    emptyIcon: string = 'glyphicon';
    nodeIcon: string = '';
    selectedIcon: string = '';
    checkedIcon: string = 'glyphicon glyphicon-check';
    partiallyCheckedIcon: string = 'glyphicon glyphicon-expand';
    uncheckedIcon: string = 'glyphicon glyphicon-unchecked';
    tagsClass: string = 'badge';

    color: string = undefined;
    backColor: string = undefined;
    borderColor: string = undefined;
    changedNodeColor: string = '#39A5DC';
    onhoverColor: string = '#F5F5F5';
    selectedColor: string = '#FFFFFF';
    selectedBackColor: string = '#428bca';
    searchResultColor: string = '#D9534F';
    searchResultBackColor: string = undefined;

    highlightSelected: boolean = true;
    highlightSearchResults: boolean = true;
    showBorder: boolean = true;
    showIcon: boolean = true;
    showImage: boolean = false;
    showCheckbox: boolean = false;
    checkboxFirst: boolean = false;
    highlightChanges: boolean = false;
    showTags: boolean = false;
    multiSelect: boolean = false;
    preventUnselect: boolean = false;
    allowReselect: boolean = false;
    hierarchicalCheck: boolean = false;
    propagateCheckEvent: boolean = false;
    wrapNodeText: boolean = false;

    // Event handlers
    onLoading: (event: Event) => void = undefined;
    onLoadingFailed: (event: Event) => void = undefined;
    onInitialized: (event: Event) => void = undefined;
    onNodeRendered: (event: Event) => void = undefined;
    onRendered: (event: Event) => void = undefined;
    onDestroyed: (event: Event) => void = undefined;

    onNodeChecked: (event: Event) => void = undefined;
    onNodeCollapsed: (event: Event) => void = undefined;
    onNodeDisabled: (event: Event) => void = undefined;
    onNodeEnabled: (event: Event) => void = undefined;
    onNodeExpanded: (event: Event) => void = undefined;
    onNodeSelected: (event: Event) => void = undefined;
    onNodeUnchecked: (event: Event) => void = undefined;
    onNodeUnselected: (event: Event) => void = undefined;

    onSearchComplete: (event: Event) => void = undefined;
    onSearchCleared: (event: Event) => void = undefined;

    lazyLoad: (node: BSTreeViewNode, renderer: (nodes: BSTreeViewNode[]) => void) => void = undefined;

    constructor(options: BSTreeViewOptions|object = null) {
        if(options) {
            Object.assign(this, options);
        }
    }
}