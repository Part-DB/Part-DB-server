import {Controller} from "@hotwired/stimulus";
import Bloodhound from "corejs-typeahead/dist/bloodhound";
import 'corejs-typeahead';
import '../../js/lib/tagsinput';
import '../../css/tagsinput.css'


export default class extends Controller {
    connect() {
        if(this.element.dataset.autocomplete) {
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
        }
    }
}