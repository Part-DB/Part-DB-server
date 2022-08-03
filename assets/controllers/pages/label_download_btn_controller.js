import {Controller} from "@hotwired/stimulus";

export default class extends Controller
{
    download(event) {
        this.element.href = document.getElementById('pdf_preview').data
    }
}