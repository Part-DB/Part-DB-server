import DatatablesController from "./datatables_controller.js";

/**
 * This is the datatables controller for log pages, it includes an mechanism to color lines based on their level.
 */
export default class extends DatatablesController {
    _rowCallback(row, data, index) {
        //Check if we have a level, then change color of this row
        if (data.level) {
            let style = "";
            switch (data.level) {
                case "emergency":
                case "alert":
                case "critical":
                case "error":
                    style = "table-danger";
                    break;
                case "warning":
                    style = "table-warning";
                    break;
                case "notice":
                    style = "table-info";
                    break;
            }

            if (style) {
                $(row).addClass(style);
            }
        }
    }
}

