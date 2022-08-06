export default class BSTreeViewEventOptions {
    silent: boolean = false;
    ignoreChildren: boolean = false;

    lazyLoad: boolean = false;

    constructor(options: BSTreeViewEventOptions|object = null) {
        if(options) {
            Object.assign(this, options);
        }
    }
}