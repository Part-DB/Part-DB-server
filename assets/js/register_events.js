'use strict';

const RegisterEventHelper = class {
    constructor() {
        this.registerToasts();
    }

    registerLoadHandler(fn) {
        document.addEventListener('turbo:load', fn);
    }

    registerToasts() {
        this.registerLoadHandler(() =>  $(".toast").toast('show'));
    }
}

export default new RegisterEventHelper();