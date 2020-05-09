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
use App\Exceptions\TwigModeException;
use App\Services\ElementTypeNameGenerator;
use App\Services\LabelSystem\Barcodes\BarcodeContentGenerator;
use Symfony\Component\Security\Core\Security;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Error\SyntaxError;

final class LabelHTMLGenerator
{
    protected $twig;
    protected $elementTypeNameGenerator;
    protected $replacer;
    protected $barcodeGenerator;
    protected $sandboxedTwigProvider;
    protected $partdb_title;
    protected $security;

    public function __construct(ElementTypeNameGenerator $elementTypeNameGenerator, LabelTextReplacer $replacer, Environment $twig,
        BarcodeGenerator $barcodeGenerator, SandboxedTwigProvider $sandboxedTwigProvider, Security $security, string $partdb_title)
    {
        $this->twig = $twig;
        $this->elementTypeNameGenerator = $elementTypeNameGenerator;
        $this->replacer = $replacer;
        $this->barcodeGenerator = $barcodeGenerator;
        $this->sandboxedTwigProvider = $sandboxedTwigProvider;
        $this->security = $security;
        $this->partdb_title = $partdb_title;
    }

    public function getLabelHTML(LabelOptions $options, array $elements): string
    {
        if (empty($elements)) {
            throw new \InvalidArgumentException('$elements must not be empty');
        }

        $twig_elements = [];

        if ($options->getLinesMode() === 'twig') {
            $sandboxed_twig = $this->sandboxedTwigProvider->getTwig($options);
            $current_user = $this->security->getUser();
        }

        $page = 1;
        foreach ($elements as $element) {
            if ($options->getLinesMode() === 'twig' && isset($sandboxed_twig) && isset($current_user)) {
                try {
                    $lines = $sandboxed_twig->render(
                        'lines',
                        [
                            'element' => $element,
                            'page' => $page,
                            'user' => $current_user,
                            'install_title' => $this->partdb_title,
                        ]
                    );
                } catch (Error $exception) {
                    throw new TwigModeException($exception);
                }
            } else {
                $lines = $this->replacer->replace($options->getLines(), $element);
            }

            $twig_elements[] = [
                'element' => $element,
                'lines' => $lines,
                'barcode' => $this->barcodeGenerator->generateSVG($options, $element),
                'barcode_content' => $this->barcodeGenerator->getContent($options, $element),
            ];

            $page++;
        }


        return $this->twig->render('LabelSystem/labels/base_label.html.twig', [
            'meta_title' => $this->getPDFTitle($options, $elements[0]),
            'elements' => $twig_elements,
            'options' => $options,
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