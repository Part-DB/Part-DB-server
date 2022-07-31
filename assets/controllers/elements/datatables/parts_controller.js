import DatatablesController from "./datatables_controller.js";

/**
 * This is the datatables controller for parts lists
 */
export default class extends DatatablesController {

    static targets = ['dt', 'selectPanel', 'selectIDs', 'selectCount', 'selectTargetPicker'];

    isSelectable() {
        //Parts controller is always selectable
        return true;
    }

    _onSelectionChange(e, dt, items) {
        const selected_elements = dt.rows({selected: true});
        const count = selected_elements.count();

        const selectPanel = this.selectPanelTarget;

        //Hide/Unhide panel with the selection tools
        if (count > 0) {
            selectPanel.classList.remove('d-none');
        } else {
            selectPanel.classList.add('d-none');
        }

        //Update selection count text
        this.selectCountTarget.innerText = count;

        //Fill selection ID input
        let selected_ids_string = selected_elements.data().map(function(value, index) {
            return value['id']; }
        ).join(",");

        this.selectIDsTarget.value = selected_ids_string;
    }

    updateOptions(select_element, json)
    {
        //Clear options
        select_element.innerHTML = null;
        $(select_element).selectpicker('destroy');

        for(let i=0; i<json.length; i++) {
            let json_opt = json[i];
            let opt = document.createElement('option');
            opt.value = json_opt.value;
            opt.innerHTML = json_opt.text;

            if(json_opt['data-subtext']) {
                opt.dataset.subtext = json_opt['data-subtext'];
            }

            select_element.appendChild(opt);
        }

        $(select_element).selectpicker('show');

    }

    updateTargetPicker(event) {
        const element = event.target;

        //Extract the url from the selected option
        const selected_option = element.options[element.options.selectedIndex];
        const url = selected_option.dataset.url;

        const select_target = this.selectTargetPickerTarget;

        if (url) {
            fetch(url)
                .then(response => {
                    response.json().then(json => {
                        this.updateOptions(select_target, json);
                    });
                });
        } else {
            $(select_target).selectpicker('hide');
        }
    }
}

