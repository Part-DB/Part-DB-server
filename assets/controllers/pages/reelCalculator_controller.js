import {Controller} from "@hotwired/stimulus";
import * as bootbox from "bootbox";

export default class extends Controller {

    static values = {
        errorMissingValues: String,
        errorOuterGreaterInner: String,
    }


    updateReelCalc() {
        const dia_inner = document.getElementById('reel_dia_inner').value;
        const dia_outer = document.getElementById('reel_dia_outer').value;
        const tape_thickness = document.getElementById('reel_tape_thick').value;
        const part_distance = document.getElementById('reel_part_distance').value;

        if (dia_inner == "" || dia_outer == "" || tape_thickness == "") {
            bootbox.alert(this.errorMissingValuesValue);
            return;
        }

        if (dia_outer**dia_outer < dia_inner**dia_inner) {
            bootbox.alert(this.errorOuterGreaterInnerValue);
            return;
        }

        const length = Math.PI * (dia_outer * dia_outer - dia_inner * dia_inner ) / (4 * tape_thickness);

        let length_formatted = length.toFixed(2) + ' mm';

        if (length > 1000) {
            length_formatted = (length / 1000).toFixed(2) + ' m';
        } else if (length > 10) {
            length_formatted = (length / 10).toFixed(2) + ' cm';
        }

        document.getElementById('result_length').textContent = length_formatted;

        //Skip if no part_distance was given
        if (part_distance == "" || part_distance == 0) {
            return;
        }

        var parts_per_meter = 1 / (part_distance / 1000);

        document.getElementById('result_parts_per_meter').textContent = parts_per_meter.toFixed(2) + ' 1/m';

        var parts_amount = (length/1000) * parts_per_meter;

        document.getElementById('result_amount').textContent = Math.floor(parts_amount);
    }
}