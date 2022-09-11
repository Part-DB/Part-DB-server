const DEFAULT_OPTIONS = {
    true:            "true",
    false:          "false",
    null:      "indeterminate",
};

/**
 * A simple tristate checkbox
 */
export default class TristateCheckbox {

    static instances = new Map();

    /**
     *
     * @type {null|boolean}
     * @private
     */
    _state = false;

    /**
     * The element representing the checkbox.
     * @type {HTMLInputElement}
     * @private
     */
    _element = null;

    /**
     * The hidden input element representing the value of the checkbox
     * @type {HTMLInputElement}
     * @private
     */
    _hiddenInput = null;

    /**
     * The values of the checkbox.
     * @type {{null: string, true: string, false: string}}
     * @private
     */
    _options = DEFAULT_OPTIONS;

    /**
     * Retrieve the instance of the TristateCheckbox for the given element if already existing, otherwise a new one is created.
     * @param element
     * @param options
     * @return {any}
     */
    static getInstance(element, options = {})
    {
        if(!TristateCheckbox.instances.has(element)) {
            TristateCheckbox.instances.set(element, new TristateCheckbox(element, options));
        }

        return TristateCheckbox.instances.get(element);
    }

    /**
     * @param {HTMLElement} element
     */
    constructor(element, options = {})
    {
        if(!element instanceof HTMLInputElement || !(element.tagName === 'INPUT' && element.type === 'checkbox')) {
            throw new Error("The given element is not an input checkbox");
        }

        //Apply options
        this._options = Object.assign(this._options, options);

        this._element = element;

        //Set the state of our element to the value of the passed input value
        this._parseInitialState();

        //Create a hidden input field to store the value of the checkbox, because this will be always be submitted in the form
        this._hiddenInput = document.createElement('input');
        this._hiddenInput.type = 'hidden';
        this._hiddenInput.name = this._element.name;
        this._hiddenInput.value = this._element.value;

        //Insert the hidden input field after the checkbox and remove the checkbox from form submission (by removing the name property)
        element.after(this._hiddenInput);
        this._element.removeAttribute('name');

        //Do a refresh to set the correct styling of the checkbox
        this._refresh();

        this._element.addEventListener('click', this.click.bind(this));
    }

    /**
     * Parse the attributes of the checkbox and set the correct state.
     * @private
     */
    _parseInitialState()
    {
        if(this._element.hasAttribute('value')) {
            this._state = this._stringToState(this._element.getAttribute('value'));
            return;
        }

        if(this._element.checked) {
            this._state = true;
            return;
        }

        if(this._element.indeterminate) {
            this._state = null;
            return;
        }

        this._state = false;
    }

    _refresh()
    {
        this._element.indeterminate = this._state === null;
        this._element.checked = this._state === true;
        //Set the value field of the checkbox and the hidden input to correct value
        this._element.value = this._stateToString(this._state);
        this._hiddenInput.value = this._stateToString(this._state);
    }


    /**
     * Returns the current state of the checkbox. True if checked, false if unchecked, null if indeterminate.
     * @return {boolean|null}
     */
    get state() {
        return this._state;
    }

    /**
     * Sets the state of the checkbox. True if checked, false if unchecked, null if indeterminate.
     * @param state
     */
    set state(state) {
        this._state = state;
        this._refresh();
    }

    /**
     * Returns the current state of the checkbox as string, according to the options.
     * @return {string}
     */
    get stateString() {
        return this._stateToString(this._state);
    }

    set stateString(string) {
        this.state = this._stringToState(string);
        this._refresh();
    }

    /**
     * @param {boolean|null} state
     * @return string
     * @private
     */
    _stateToString(state)
    {
        if (this.state === null) {
            return this._options.null;
        } else if (this.state === true) {
            return this._options.true;
        } else if (this.state === false) {
            return this._options.false;
        }

        throw new Error("Invalid state " + state);
    }

    /**
     * Converts a string to a state according to the options.
     * @param string
     * @param throwError
     * @return {null|boolean}
     * @private
     */
    _stringToState(string, throwError = true)
    {
        if (string === this._options.true) {
            return true;
        } else if (string === this._options.false) {
            return false;
        } else if (string === this._options.null) {
            return null;
        }

        if(throwError) {
            throw new Error("Invalid state string " + string);
        } else {
            return null;
        }
    }

    click()
    {
        switch (this._state) {
            case true:  this._state = false; break;
            case false: this._state = null; break;
            default:    this._state = true; break;
        }

        this._refresh();
    }

}