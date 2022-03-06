import {Controller} from "@hotwired/stimulus";

import "katex";
import 'katex/dist/katex.css';
import renderMathInElement from "katex/dist/contrib/auto-render";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        this.applyLatex();
        this.element.addEventListener('markdown:finished', () => this.applyLatex());
    }

    applyLatex() {
        renderMathInElement(this.element, {
            delimiters: [
                {left: "$$", right: "$$", display: true},
                {left: "$", right: "$", display: false},
                {left: "\\(", right: "\\)", display: false},
                {left: "\\[", right: "\\]", display: true}
            ]
        });
    }

    mutate() {
        this.applyLatex();
    }
}