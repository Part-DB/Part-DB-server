/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

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