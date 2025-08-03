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

import SpecialCharacters from 'ckeditor5';
import SpecialCharactersEssentials from 'ckeditor5';

import {Plugin} from 'ckeditor5';

const emoji = require('emoji.json');

export default class SpecialCharactersEmoji extends Plugin {

    init() {
        const editor = this.editor;
        const specialCharsPlugin = editor.plugins.get('SpecialCharacters');

        //Add greek characters to special characters
        specialCharsPlugin.addItems('Greek', this.getGreek());

        //Add Emojis to special characters
        specialCharsPlugin.addItems('Emoji', this.getEmojis());
    }

    getGreek() {
        return [
            { title: 'Alpha', character: 'Α' },
            { title: 'Beta', character: 'Β' },
            { title: 'Gamma', character: 'Γ' },
            { title: 'Delta', character: 'Δ' },
            { title: 'Epsilon', character: 'Ε' },
            { title: 'Zeta', character: 'Ζ' },
            { title: 'Eta', character: 'Η' },
            { title: 'Theta', character: 'Θ' },
            { title: 'Iota', character: 'Ι' },
            { title: 'Kappa', character: 'Κ' },
            { title: 'Lambda', character: 'Λ' },
            { title: 'Mu', character: 'Μ' },
            { title: 'Nu', character: 'Ν' },
            { title: 'Xi', character: 'Ξ' },
            { title: 'Omicron', character: 'Ο' },
            { title: 'Pi', character: 'Π' },
            { title: 'Rho', character: 'Ρ' },
            { title: 'Sigma', character: 'Σ' },
            { title: 'Tau', character: 'Τ' },
            { title: 'Upsilon', character: 'Υ' },
            { title: 'Phi', character: 'Φ' },
            { title: 'Chi', character: 'Χ' },
            { title: 'Psi', character: 'Ψ' },
            { title: 'Omega', character: 'Ω' },
            { title: 'alpha', character: 'α' },
            { title: 'beta', character: 'β' },
            { title: 'gamma', character: 'γ' },
            { title: 'delta', character: 'δ' },
            { title: 'epsilon', character: 'ε' },
            { title: 'zeta', character: 'ζ' },
            { title: 'eta', character: 'η' },
            { title: 'theta', character: 'θ' },
            { title: 'alternate theta', character: 'ϑ' },
            { title: 'iota', character: 'ι' },
            { title: 'kappa', character: 'κ' },
            { title: 'lambda', character: 'λ' },
            { title: 'mu', character: 'μ' },
            { title: 'nu', character: 'ν' },
            { title: 'xi', character: 'ξ' },
            { title: 'omicron', character: 'ο' },
            { title: 'pi', character: 'π' },
            { title: 'rho', character: 'ρ' },
            { title: 'sigma', character: 'σ' },
            { title: 'tau', character: 'τ' },
            { title: 'upsilon', character: 'υ' },
            { title: 'phi', character: 'φ' },
            { title: 'chi', character: 'χ' },
            { title: 'psi', character: 'ψ' },
            { title: 'omega', character: 'ω' },
            { title: 'digamma', character: 'Ϝ' },
            { title: 'stigma', character: 'Ϛ' },
            { title: 'heta', character: 'Ͱ' },
            { title: 'sampi', character: 'Ϡ' },
            { title: 'koppa', character: 'Ϟ' },
            { title: 'san', character: 'Ϻ' },
        ];
    }

    getEmojis() {
        //Map our emoji data to the format the plugin expects
        return emoji.map(emoji => {
            return {
                title: emoji.name,
                character: emoji.char
            };
        });
    }
}
