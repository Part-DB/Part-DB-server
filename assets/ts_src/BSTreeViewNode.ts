import BSTreeViewNodeState from "./BSTreeViewNodeState";
import BSTreeViewOptions from "./BSTreeViewOptions";

export default class BSTreeViewNode {
    text: string;
    icon: string;
    image: string;
    selectedIcon: string;
    color: string;
    backColor: string;
    iconColor: string;
    iconBackground: string;
    selectable: boolean;
    checkable: boolean;
    state: BSTreeViewNodeState;
    tags: string[];
    dataAttr: object;
    id: string;
    class: string;
    hideCheckbox: boolean;
    nodes: BSTreeViewNode[];
    tooltip: string;
    href: string;

    lazyLoad: boolean;
    tagsClass: string;


    el: HTMLElement;

    searchResult: boolean;


    level: number;
    index: number;
    nodeId: string;
    parentId: string

    constructor(options: BSTreeViewNode|object = null) {
        if(options) {
            Object.assign(this, options);
        }
    }
}

