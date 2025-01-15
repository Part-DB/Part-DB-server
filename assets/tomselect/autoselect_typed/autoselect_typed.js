/**
 * Autoselect Typed plugin for Tomselect
 *
 * This plugin allows automatically selecting an option matching the typed text when the Tomselect element goes out of
 * focus (is blurred) and/or when the delimiter is typed.
 *
 * #select_on_blur option
 * Tomselect natively supports the "createOnBlur" option. This option picks up any remaining text in the input field
 * and uses it to create a new option and selects that option. It does behave a bit strangely though, in that it will
 * not select an already existing option when the input is blurred, so if you typed something that matches an option in
 * the list and then click outside the box (without pressing enter) the entered text is just removed (unless you have
 * allow duplicates on in which case it will create a new option).
 * This plugin fixes that, such that Tomselect will first try to select an option matching the remaining uncommitted
 * text and only when no matching option is found tries to create a new one (if createOnBlur and create is on)
 *
 * #select_on_delimiter option
 * Normally when typing the delimiter (space by default) Tomselect will try to create a new option (and select it) (if
 * create is on), but if the typed text matches an option (and allow duplicates is off) it refuses to react at all until
 * you press enter. With this option, the delimiter will also allow selecting an option, not just creating it.
 */
function select_current_input(self){
    if(self.isLocked){
        return
    }

    const val = self.inputValue()
    if (self.options[val]) {
        self.addItem(val)
        self.setTextboxValue()
    }
}

export default function(plugin_options_) {
    const plugin_options = Object.assign({
        //Autoselect the typed text when the input element goes out of focus
        select_on_blur: true,
        //Autoselect the typed text when the delimiter is typed
        select_on_delimiter: true,
    }, plugin_options_);

    const self = this

    if(plugin_options.select_on_blur) {
        this.hook("before", "onBlur", function () {
            select_current_input(self)
        })
    }

    if(plugin_options.select_on_delimiter) {
        this.hook("before", "onKeyPress", function (e) {
            const character = String.fromCharCode(e.keyCode || e.which);
            if (self.settings.mode === 'multi' && character === self.settings.delimiter) {
                select_current_input(self)
            }
        })
    }

}