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

    /**
     * Geometry of the legacy QR layout (label_page_qr.html.twig), as fractions of the usable label width:
     * The .col-5 column with 0.5% margin, the QR code with max-width 80% of it, and the .col-7 text column (see label_style.css.twig)
     */
    private const LEGACY_2D_LAYOUT = [
        'barcodeLeft' => 0.005,
        'barcodeWidth' => 0.3766 * 0.8,
        'textLeft' => 0.3916,
        'textWidth' => 0.5433,
    ];
    /**
     * Geometry of the legacy 1D layout (label_page_1d.html.twig) in mm: The barcode image (max-width 150px, height 30px)
     * with its caption, positioned 32px above the bottom of the page content (see .C39-container in label_style.css.twig)
     */
    private const LEGACY_1D_LAYOUT = [
        'maxWidth' => 39.6875,
        'height' => 7.9375,
        'captionHeight' => 2.709,
        'bottom' => 8.4667,
    ];

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
        ]);
    }

    /**
     * Returns the parameters needed by the label editor, to show the label content and the barcode in the same size and
     * position as on the label (see labelGeometry.js), without duplicating the values.
     * All sizes are in mm, all fractions are relative to the usable label width.
     * @return array<string, mixed>
     */
    public static function getLabelEditorParameters(): array
    {
        return [
            'pageMarginX' => self::PAGE_MARGIN_X,
            'pageMarginY' => self::PAGE_MARGIN_Y,
            'types1D' => array_values(array_map(static fn(BarcodeType $type): string => $type->value,
                array_filter(BarcodeType::cases(), static fn(BarcodeType $type): bool => $type->is1D()))),
            //Layouts of the barcodes (see label_page_qr/1d.html.twig and label_style.css.twig)
            'legacy2D' => self::LEGACY_2D_LAYOUT,
            'legacy1D' => self::LEGACY_1D_LAYOUT,
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
