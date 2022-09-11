import {Controller} from "@hotwired/stimulus";
import katex from "katex";
import "katex/dist/katex.css";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ["input", "preview"];

    updatePreview()
    {
        katex.render(this.inputTarget.value, this.previewTarget, {
            throwOnError: false,
        });
    }

    connect()
    {
        this.updatePreview();
        this.inputTarget.addEventListener('input', this.updatePreview.bind(this));
    }
}