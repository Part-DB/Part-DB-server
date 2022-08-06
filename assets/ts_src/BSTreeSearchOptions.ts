import BSTreeViewEventOptions from "./BSTreeViewEventOptions";

export default class BSTreeSearchOptions extends BSTreeViewEventOptions {
    ignoreCase: boolean = true;
    exactMatch: boolean = false;
    revealResults: boolean = true;
}