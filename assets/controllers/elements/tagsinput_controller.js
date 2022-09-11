import {Controller} from "@hotwired/stimulus";

import "tom-select/dist/css/tom-select.bootstrap5.css";
import '../../css/tom-select_extensions.css';
import TomSelect from "tom-select";

export default class extends Controller {
    _tomSelect;

    connect() {
        let settings = {
            plugins: {
                remove_button:{
                }
            },
            persistent: false,
            createOnBlur: true,
            create: true,
        };

        if(this.element.dataset.autocomplete) {
            const base_url = this.element.dataset.autocomplete;
            settings.load = (query, callback) => {
                if(query.length < 2){
                    callback();
                    return;
                }
                const url = base_url.replace('__QUERY__', encodeURIComponent(query));

                fetch(url)
                    .then(response => response.json())
                    .then(json => {
                        const data = json.map(x => {return {"value": x, "text": x}});
                        callback(data);
                    }).catch(()=>{
                    callback();
                });
            }
        }

        this._tomSelect = new TomSelect(this.element, settings);

        /*if(this.element.dataset.autocomplete) {
            const engine = new Bloodhound({
                //@ts-ignore
                datumTokenizer: Bloodhound.tokenizers.obj.whitespace(''),
                //@ts-ignore
                queryTokenizer: Bloodhound.tokenizers.obj.whitespace(''),
                remote: {
                    url: this.element.dataset.autocomplete,
                    wildcard: 'QUERY'
                }
            });

            $(this.element).tagsinput({
                typeaheadjs: {
                    name: 'tags',
                    source: engine.ttAdapter()
                }
            });
        } else { // Init tagsinput without typeahead
            $(this.element).tagsinput();
        }*/


    }
}