import {Controller} from "@hotwired/stimulus";

import "tom-select/dist/css/tom-select.bootstrap5.css";
import '../../css/tom-select_extensions.css';
import TomSelect from "tom-select";

export default class extends Controller {
    _tomSelect;

    connect() {

        let settings = {
            persistent: false,
            create: true,
            maxItems: 1,
            createOnBlur: true,
            render: {
                item: (data, escape) => {
                    return '<span>' + escape(data.label) + '</span>';
                },
                option: (data, escape) => {
                    if (data.image) {
                        return "<div class='row m-0'><div class='col-2 pl-0 pr-1'><img class='typeahead-image' src='" + data.image + "'/></div><div class='col-10'>" + data.label + "</div></div>"
                    }
                    return '<div>' + escape(data.label) + '</div>';
                }
            }
        };

        if(this.element.dataset.autocomplete) {
            const base_url = this.element.dataset.autocomplete;
            settings.searchField = "label";
            settings.sortField = "label";
            settings.valueField = "label";
            settings.load = (query, callback) => {
                if(query.length < 2){
                    callback();
                    return;
                }
                const url = base_url.replace('__QUERY__', encodeURIComponent(query));

                fetch(url)
                    .then(response => response.json())
                    .then(json => {
                        const data = json.map(x => {
                            return {
                                "label": x.name,
                                "image": x.image,
                            }
                        });
                        callback(data);
                    }).catch(()=>{
                    callback();
                });
            };
        }
        this._tomSelect = new TomSelect(this.element, settings);
    }

}
