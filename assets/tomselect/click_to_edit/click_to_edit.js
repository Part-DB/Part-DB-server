/**
 * click_to_edit plugin for Tomselect
 *
 * This plugin allows editing (and selecting text in) any selected item by clicking it.
 *
 * Usually, when the user typed some text and created an item in Tomselect that item cannot be edited anymore. To make
 * a change, the item has to be deleted and retyped completely. There is also generally no way to copy text out of a
 * tomselect item. The "restore_on_backspace" plugin improves that somewhat, by allowing the user to edit an item after
 * pressing backspace. However, it is somewhat confusing to first have to focus the field an then hit backspace in order
 * to copy a piece of text. It may also not be immediately obvious for editing.
 * This plugin transforms an item into editable text when it is clicked, e.g. when the user tries to place the caret
 * within an item or when they try to drag across the text to highlight it.
 * It also plays nice with the remove_button plugin which still removes (deselects) an option entirely.
 *
 * It is recommended to also enable the autoselect_typed plugin when using this plugin. Without it, the text in the
 * input field (i.e. the item that was just clicked) is lost when the user clicks outside the field. Also, when the user
 * clicks an option (making it text) and then tries to enter another one by entering the delimiter (e.g. space) nothing
 * happens until enter is pressed or the text is changed from what it was.
 */

/**
 * Return a dom element from either a dom query string, jQuery object, a dom element or html string
 * https://stackoverflow.com/questions/494143/creating-a-new-dom-element-from-an-html-string-using-built-in-dom-methods-or-pro/35385518#35385518
 *
 * param query should be {}
 */
const getDom = query => {
    if (query.jquery) {
        return query[0];
    }
    if (query instanceof HTMLElement) {
        return query;
    }
    if (isHtmlString(query)) {
        var tpl = document.createElement('template');
        tpl.innerHTML = query.trim(); // Never return a text node of whitespace as the result
        return tpl.content.firstChild;
    }
    return document.querySelector(query);
};
const isHtmlString = arg => {
    if (typeof arg === 'string' && arg.indexOf('<') > -1) {
        return true;
    }
    return false;
};

function plugin(plugin_options_) {
    const self = this

    const plugin_options = Object.assign({
        //If there is unsubmitted text in the input field, should that text be automatically used to select a matching
        //element? If this is off, clicking on item1 and then clicking on item2 will result in item1 being deselected
        auto_select_before_edit: true,
        //If there is unsubmitted text in the input field, should that text be automatically used to create a matching
        //element if no matching element was found or auto_select_before_edit is off?
        auto_create_before_edit: true,
        //customize this function to change which text the item is replaced with when clicking on it
        text: option => {
            return option[self.settings.labelField];
        }
    }, plugin_options_);


    self.hook('after', 'setupTemplates', () => {
        const orig_render_item = self.settings.render.item;
        self.settings.render.item = (data, escape) => {
            const item = getDom(orig_render_item.call(self, data, escape));

            item.addEventListener('click', evt => {
                    if (self.isLocked) {
                        return;
                    }
                    const val = self.inputValue();

                    if (self.options[val]) {
                        self.addItem(val)
                    } else if (self.settings.create) {
                        self.createItem();
                    }
                    const option = self.options[item.dataset.value]
                    self.setTextboxValue(plugin_options.text.call(self, option));
                    self.focus();
                    self.removeItem(item);
                }
            );

            return item;
        }
    });

}
export { plugin as default };