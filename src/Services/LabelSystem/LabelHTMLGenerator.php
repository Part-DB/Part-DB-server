<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Services\LabelSystem;

use App\Entity\Contracts\NamedElementInterface;
use App\Entity\LabelSystem\LabelOptions;
use App\Services\ElementTypeNameGenerator;
use Twig\Environment;

class LabelHTMLGenerator
{
    protected $twig;
    protected $elementTypeNameGenerator;
    protected $replacer;

    public function __construct(ElementTypeNameGenerator $elementTypeNameGenerator, LabelTextReplacer $replacer, Environment $twig)
    {
        $this->twig = $twig;
        $this->elementTypeNameGenerator = $elementTypeNameGenerator;
        $this->replacer = $replacer;
    }

    public function getLabelHTML(LabelOptions $options, object $element): string
    {
        return $this->twig->render('LabelSystem/labels/base_label.html.twig', [
            'meta_title' => $this->getPDFTitle($options, $element),
            'lines' => $this->replacer->replace($options->getLines(), $element),
        ]);
    }

    protected function getPDFTitle(LabelOptions $options, object $element)
    {
        if ($element instanceof NamedElementInterface) {
            return $this->elementTypeNameGenerator->getTypeNameCombination($element, false);
        }

        return 'Part-DB label';
    }
}