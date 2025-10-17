import {Controller} from "@hotwired/stimulus";

import "tom-select/dist/css/tom-select.bootstrap5.css";
import '../../css/components/tom-select_extensions.css';
import TomSelect from "tom-select";
import {marked} from "marked";

export default class extends Controller {
    _tomSelect;

    connect() {

        let settings = {
            allowEmptyOption: true,
            plugins: ['dropdown_input', 'clear_button'],
            searchField: ["name", "description", "category", "footprint"],
            valueField: "id",
            labelField: "name",
            dropdownParent: 'body',
            preload: "focus",
            render: {
                item: (data, escape) => {
                    return '<span>' + (data.image ? "<img style='height: 1.5rem; margin-right: 5px;' ' src='" + data.image + "'/>" : "") + escape(data.name) +  '</span>';
                },
                option: (data, escape) => {
                    if(data.text) {
                        return '<span>' + escape(data.text) + '</span>';
                    }

                    let tmp = '<div class="row m-0">' +
                        "<div class='col-2 p-0 d-flex align-items-center' style='max-width: 80px;'>" +
                        (data.image ? "<img class='typeahead-image' src='" + data.image + "'/>" : "") +
                        "</div>" +
                        "<div class='col-10'>" +
                        '<h6 class="m-0">' + escape(data.name) + '</h6>' +
                        (data.description ? '<p class="m-0">' + marked.parseInline(data.description) + '</p>' : "") +
                        (data.category ? '<p class="m-0"><span class="fa-solid fa-tags fa-fw"></span> ' + escape(data.category) : "");

                    if (data.footprint) { //If footprint is defined for the part show it next to the category
                        tmp += ' <span class="fa-solid fa-microchip fa-fw"></span> ' + escape(data.footprint);
                    }

                    return tmp + '</p>' +
                        '</div></div>';
                }
            }
        };


        if (this.element.dataset.autocomplete) {
            const base_url = this.element.dataset.autocomplete;
            settings.valueField = "id";
            settings.load = (query, callback) => {
                const url = base_url.replace('__QUERY__', encodeURIComponent(query));

                fetch(url)
                    .then(response => response.json())
                    .then(json => {callback(json);})
                    .catch(() => {
                        callback()
                    });
            };


            this._tomSelect = new TomSelect(this.element, settings);
            //this._tomSelect.clearOptions();
        }
    }

    disconnect() {
        super.disconnect();
        //Destroy the TomSelect instance
        this._tomSelect.destroy();
    }
}
