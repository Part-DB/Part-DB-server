import {Controller} from "@hotwired/stimulus";

//import "katex";
import 'katex/dist/katex.css';
import {auto} from "@popperjs/core";
//import renderMathInElement from "katex/dist/contrib/auto-render";

export default class extends Controller {
    connect() {
        this.applyLatex();
        this.element.addEventListener('markdown:finished', () => this.applyLatex());
    }

    applyLatex() {
        //Only import the katex library, if we have an delimiter string in our element text
        let str = this.element.textContent;
        if(str.match(/(\$|\\\(|\\\[).+(\$|\\\)|\\\])/)) {
            import('katex/dist/contrib/auto-render').then((autorender) => {
                //This calls renderMathInElement()
                autorender.default(this.element, {
                    delimiters: [
                        {left: "$$", right: "$$", display: true},
                        {left: "$", right: "$", display: false},
                        {left: "\\(", right: "\\)", display: false},
                        {left: "\\[", right: "\\]", display: true}
                    ]
                });
            })
        }

    }

    mutate() {
        this.applyLatex();
    }
}