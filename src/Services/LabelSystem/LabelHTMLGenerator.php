<?php
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

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\LabelSystem\BarcodeType;
use App\Entity\LabelSystem\LabelProcessMode;
use App\Settings\SystemSettings\CustomizationSettings;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\LabelSystem\LabelOptions;
use App\Exceptions\TwigModeException;
use App\Services\ElementTypeNameGenerator;
use InvalidArgumentException;
use Twig\Environment;

final readonly class LabelHTMLGenerator
{
    /** The horizontal page margin in mm (6px, see label_style.css.twig) */
    private const PAGE_MARGIN_X = 1.5875;
    /** The vertical page margin in mm (12px, see label_style.css.twig) */
    private const PAGE_MARGIN_Y = 3.175;
    /** The default maximum width of 1D barcodes in mm (150px) */
    private const DEFAULT_1D_MAX_WIDTH = 39.6875;
    /** The height of 1D barcodes in mm (30px) */
    private const DEFAULT_1D_HEIGHT = 7.9375;
    /** The default width of 2D barcodes, relative to the usable label width */
    private const DEFAULT_2D_WIDTH_FACTOR = 0.3;
    /** The gap between 1D barcodes and the text above them in mm (see label_style.css.twig) */
    private const STACKED_GAP = 1.0;
    /** The height of the caption below 1D barcodes in mm (6pt font size, plus some safety margin) */
    private const CAPTION_HEIGHT = 2.5;

    public function __construct(
        private ElementTypeNameGenerator $elementTypeNameGenerator,
        private LabelTextReplacer $replacer,
        private Environment $twig,
        private LabelBarcodeGenerator $barcodeGenerator,
        private SandboxedTwigFactory $sandboxedTwigProvider,
        private Security $security,
        private CustomizationSettings $customizationSettings)
    {
    }

    public function getLabelHTML(LabelOptions $options, array $elements): string
    {
        if ($elements === []) {
            throw new InvalidArgumentException('$elements must not be empty');
        }

        $twig_elements = [];

        if (LabelProcessMode::TWIG === $options->getProcessMode()) {
            $sandboxed_twig = $this->sandboxedTwigProvider->createSandbox($options);
            $current_user = $this->security->getUser();
        }

        $barcode_layout = $this->getBarcodeLayout($options);

        $page = 1;
        foreach ($elements as $element) {
            if (isset($sandboxed_twig, $current_user) && LabelProcessMode::TWIG === $options->getProcessMode()) {
                try {
                    $lines = $sandboxed_twig->render(
                        'lines',
                        [
                            'element' => $element,
                            'page' => $page,
                            'last_page' => count($elements),
                            'user' => $current_user,
                            'install_title' => $this->customizationSettings->instanceName,
                            'partdb_title' => $this->customizationSettings->instanceName,
                            'paper_width' => $options->getWidth(),
                            'paper_height' => $options->getHeight(),
                        ]
                    );
                } catch (\Throwable $exception) {
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

            ++$page;
        }

        return $this->twig->render('label_system/labels/base_label.html.twig', [
            'meta_title' => $this->getPDFTitle($options, $elements[0]),
            'elements' => $twig_elements,
            'options' => $options,
            'barcode_layout' => $barcode_layout,
        ]);
    }

    /**
     * Determines the size (in mm) of the barcode and the layout used.
     * By default, 2D barcodes are placed left of the text and 1D barcodes below it.
     * @return array{side: bool, width: float, height: float, cell_height: float}|null
     */
    public function getBarcodeLayout(LabelOptions $options): ?array
    {
        $type = $options->getBarcodeType();
        if ($type === BarcodeType::NONE) {
            return null;
        }

        //The area usable for content, without the page margins defined in label_style.css.twig
        $content_width = max($options->getWidth() - 2 * self::PAGE_MARGIN_X, 1.0);
        $content_height = max($options->getHeight() - 2 * self::PAGE_MARGIN_Y, 1.0);

        if ($type->is2D()) {
            $width = $options->getBarcodeSize() ?? min($content_width * self::DEFAULT_2D_WIDTH_FACTOR, $content_height);
            $height = $width;
        } else {
            $width = $options->getBarcodeSize() ?? min(self::DEFAULT_1D_MAX_WIDTH, $content_width);
            $height = self::DEFAULT_1D_HEIGHT;
        }

        //dompdf does not distribute the table height to its rows, so we have to calculate the height of the cells ourselves.
        //For 1D barcodes below the text, the text cell gets all the space not used by the barcode (and its caption).
        $barcode_block_height = $height + self::STACKED_GAP + self::CAPTION_HEIGHT;

        return [
            'side' => $type->is2D(),
            'width' => round($width, 3),
            'height' => round($height, 3),
            'cell_height' => round($type->is2D() ? $content_height : max($content_height - $barcode_block_height, 0.0), 3),
        ];
    }

    private function getPDFTitle(LabelOptions $options, object $element): string
    {
        if ($element instanceof NamedElementInterface) {
            return $this->elementTypeNameGenerator->getTypeNameCombination($element, false);
        }

        return 'Part-DB label';
    }
}
