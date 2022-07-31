import {Controller} from "@hotwired/stimulus";

//Styles
import 'datatables.net-bs5/css/dataTables.bootstrap5.css'
import 'datatables.net-buttons-bs5/css/buttons.bootstrap5.css'
import 'datatables.net-fixedheader-bs5/css/fixedHeader.bootstrap5.css'
import 'datatables.net-select-bs5/css/select.bootstrap5.css'
import 'datatables.net-responsive-bs5/css/responsive.bootstrap5.css';

//JS
import 'datatables.net-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/buttons.colVis.js';
import 'datatables.net-fixedheader-bs5';
import 'datatables.net-select-bs5';
import 'datatables.net-colreorder-bs5';
import 'datatables.net-responsive-bs5';
import '../../../js/lib/datatables';

const EVENT_DT_LOADED = 'dt:loaded';

export default class extends Controller {

    static targets = ['dt'];

    /** The datatable instance associated with this controller instance */
    _dt;

    connect() {
        //$($.fn.DataTable.tables()).DataTable().fixedHeader.disable();
        //$($.fn.DataTable.tables()).DataTable().destroy();

        const settings = JSON.parse(this.element.dataset.dtSettings);
        if(!settings) {
            throw new Error("No settings provided for datatable!");
        }

        //Add url info, as the one available in the history is not enough, as Turbo may have not changed it yet
        settings.url = this.element.dataset.dtUrl;


        //@ts-ignore
        const promise = $(this.dtTarget).initDataTables(settings,
            {
                colReorder: true,
                responsive: true,
                fixedHeader: {
                    header: $(window).width() >= 768, //Only enable fixedHeaders on devices with big screen. Fixes scrolling issues on smartphones.
                    headerOffset: $("#navbar").height()
                },
                buttons: [{
                    "extend": 'colvis',
                    'className': 'mr-2 btn-light',
                    "text": "<i class='fa fa-cog'></i>"
                }],
                select: this.isSelectable(),
                rowCallback: this._rowCallback.bind(this),
            })
            //Register error handler
            .catch(err => {
                console.error("Error initializing datatables: " + err);
            });

        //Dispatch an event to let others know that the datatables has been loaded
        promise.then((dt) => {
            const event = new CustomEvent(EVENT_DT_LOADED, {bubbles: true});
            this.element.dispatchEvent(event);

            this._dt = dt;
        });

        //Register event handlers
        promise.then((dt) => {
            dt.on('select.dt deselect.dt', this._onSelectionChange.bind(this));
        });

        //Allow to further configure the datatable
        promise.then(this._afterLoaded.bind(this));



        //Register links.
        /*promise.then(function() {

            //Set the correct title in the table.
            let title = $('#part-card-header-src');
            $('#part-card-header').html(title.html());
            $(document).trigger('ajaxUI:dt_loaded');


            if($table.data('part_table')) {
                //@ts-ignore
                $('#dt').on( 'select.dt deselect.dt', function ( e, dt, items ) {
                    let selected_elements = dt.rows({selected: true});
                    let count = selected_elements.count();

                    if(count > 0) {
                        $('#select_panel').removeClass('d-none');
                    } else {
                        $('#select_panel').addClass('d-none');
                    }

                    $('#select_count').text(count);

                    let selected_ids_string = selected_elements.data().map(function(value, index) {
                        return value['id']; }
                    ).join(",");

                    $('#select_ids').val(selected_ids_string);

                } );
            }

            //Attach event listener to update links after new page selection:
            $('#dt').on('draw.dt column-visibility.dt', function() {
                //ajaxUI.registerLinks();
                $(document).trigger('ajaxUI:dt_loaded');
            });
        });*/

        console.debug('Datatables inited.');
    }

    _rowCallback(row, data, index) {
        //Empty by default but can be overridden by child classes
    }

    _onSelectionChange(e, dt, items ) {
        //Empty by default but can be overridden by child classes
        alert("Test");
    }

    _afterLoaded(dt) {
        //Empty by default but can be overridden by child classes
    }

    /**
     * Check if this datatable has selection feature enabled
     */
    isSelectable()
    {
        return this.element.dataset.select ?? false;
    }

}