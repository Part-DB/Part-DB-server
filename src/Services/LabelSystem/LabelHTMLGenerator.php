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
use Masterminds\HTML5;
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
    /** The gap between 2D barcodes and the text right of them in mm (see label_style.css.twig) */
    private const SIDE_GAP = 4.0;
    /** The line height of the default font (DejaVu Sans Mono) in dompdf, relative to the font size: dompdf uses the
     *  height of the font instead of the line-height property (see also ckeditor.css) */
    private const DEFAULT_LINE_HEIGHT = 1.2804;
    /** CSS px per mm (dompdf uses 96dpi, like browsers) */
    private const PX_PER_MM = 96 / 25.4;
    /** The gap between 1D barcodes and the text above them in mm (see label_style.css.twig) */
    private const STACKED_GAP = 1.0;
    /** The height of the caption below 1D barcodes in mm (6pt font size, plus some safety margin) */
    private const CAPTION_HEIGHT = 2.5;

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

    /** The start of the src of the barcode placeholder image, inserted by the label editor (see PartDBLabelBarcode.js) */
    public const BARCODE_IMAGE_SRC_PREFIX = 'data:image/svg+xml;partdb=barcode';
    /** Images with this class are replaced by the barcode too (e.g. for handwritten HTML or twig mode) */
    public const BARCODE_IMAGE_CLASS = 'partdb-barcode';
    /** The start of the src of the part image placeholder, inserted by the label editor (see PartDBLabelPartImage.js) */
    public const PART_IMAGE_SRC_PREFIX = 'data:image/svg+xml;partdb=part-image';
    /** Images with this class are replaced by the part image too (e.g. for handwritten HTML or twig mode) */
    public const PART_IMAGE_CLASS = 'partdb-part-image';
    /** The size of the part image placeholder in px, if no size is given (the size of the placeholder image of the editor) */
    private const PART_IMAGE_DEFAULT_SIZE = 64.0;
    /** An empty image, used if the part has no image, so that the placeholder keeps its size */
    private const EMPTY_IMAGE = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /** Matches the CKEditor image styles, which float the image (so that the text wraps around it) */
    private const FLOATING_IMAGE_REGEX = '/image-style-(align-left|align-right|side)\b/';

    public function __construct(
        private ElementTypeNameGenerator $elementTypeNameGenerator,
        private LabelTextReplacer $replacer,
        private Environment $twig,
        private LabelBarcodeGenerator $barcodeGenerator,
        private SandboxedTwigFactory $sandboxedTwigProvider,
        private Security $security,
        private CustomizationSettings $customizationSettings,
        private LabelPartImageProvider $partImageProvider)
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

            $barcode = $this->barcodeGenerator->generateSVG($options, $element);
            [$placed_lines, $barcode_placed] = $this->processLines($lines, $element, $options, $barcode, $barcode_layout);

            $twig_elements[] = [
                'element' => $element,
                'lines' => $placed_lines ?? $lines,
                //If the barcode is placed inside the lines, we must not render it via the default layout
                'barcode_placed' => $barcode_placed,
                //dompdf can not handle floats (text wrapped images) inside the floated columns of the legacy QR layout
                'has_floating_images' => preg_match(self::FLOATING_IMAGE_REGEX, $placed_lines ?? $lines) === 1,
                'barcode' => $barcode,
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
     * Determines the size (in mm) of the barcode and the layout used if the barcode is not placed inside the lines.
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

    /**
     * Returns the parameters needed by the label editor, to show the label content and the barcode placeholder in the same
     * size and position as on the label (see labelGeometry.js), without duplicating the values.
     * All sizes are in mm, all fractions are relative to the usable label width.
     * @return array<string, mixed>
     */
    public static function getLabelEditorParameters(): array
    {
        return [
            'pageMarginX' => self::PAGE_MARGIN_X,
            'pageMarginY' => self::PAGE_MARGIN_Y,
            'default1DMaxWidth' => self::DEFAULT_1D_MAX_WIDTH,
            'default1DHeight' => self::DEFAULT_1D_HEIGHT,
            'default2DWidthFactor' => self::DEFAULT_2D_WIDTH_FACTOR,
            'types1D' => array_values(array_map(static fn(BarcodeType $type): string => $type->value,
                array_filter(BarcodeType::cases(), static fn(BarcodeType $type): bool => $type->is1D()))),
            //Layout used if a barcode size is set (see label_page_barcode.html.twig)
            'sizedLayout' => [
                'sideGap' => self::SIDE_GAP,
                'stackedGap' => self::STACKED_GAP,
                'captionHeight' => self::CAPTION_HEIGHT,
            ],
            //Legacy layouts, used if no barcode size is set (see label_page_qr/1d.html.twig and label_style.css.twig)
            'legacy2D' => self::LEGACY_2D_LAYOUT,
            'legacy1D' => self::LEGACY_1D_LAYOUT,
        ];
    }

    /**
     * Checks if the given img element is a placeholder image of the given type, inserted via the label editor
     * (identified by its src), or written by hand (identified by its class).
     */
    private function isPlaceholderImage(\DOMElement $img, string $src_prefix, string $class): bool
    {
        return str_starts_with($img->getAttribute('src'), $src_prefix)
            || in_array($class, preg_split('/\s+/', $img->getAttribute('class')) ?: [], true);
    }

    /**
     * Processes the lines of a label: Replaces the placeholder images (see replacePlaceholderImages()) and fixes the
     * position of floating images, which do not fit next to each other (see fitFloatingImages()).
     * @param  array{width: float, height: float}|null  $barcode_layout
     * @return array{string|null, bool} The modified lines (or null if they were not changed, so that labels without
     *                                   placeholders or floating images are not changed in any way), and whether a
     *                                   barcode was placed inside the lines
     */
    private function processLines(string $lines, object $element, LabelOptions $options, ?string $barcode_svg, ?array $barcode_layout): array
    {
        $has_placeholders = str_contains($lines, 'partdb=') || str_contains($lines, self::BARCODE_IMAGE_CLASS)
            || str_contains($lines, self::PART_IMAGE_CLASS);
        $has_floats = preg_match(self::FLOATING_IMAGE_REGEX, $lines) === 1;
        if (!$has_placeholders && !$has_floats) {
            return [null, false];
        }

        $html5 = new HTML5(['disable_html_ns' => true]);
        $fragment = $html5->loadHTMLFragment($lines);

        [$changed, $barcode_placed] = $has_placeholders
            ? $this->replacePlaceholderImages($fragment, $element, $barcode_svg, $barcode_layout)
            : [false, false];

        if ($has_floats) {
            $changed = $this->replaceEmptyLines($fragment) || $changed;
            $changed = $this->fitFloatingImages($fragment, $this->getAvailableWidth($options, $barcode_placed, $barcode_layout)) || $changed;
        }

        return [$changed ? $html5->saveHTML($fragment) : null, $barcode_placed];
    }

    /**
     * Replaces the placeholder images in the given lines: Barcode placeholders with the barcode of the label (sized
     * according to the layout, or removed if no barcode type is selected), and part image placeholders with the image of
     * the part (fitted into the placeholder).
     * @param  array{width: float, height: float}|null  $barcode_layout
     * @return array{bool, bool} Whether the lines were changed, and whether a barcode was placed inside the lines
     */
    private function replacePlaceholderImages(\DOMDocumentFragment $fragment, object $element, ?string $barcode_svg, ?array $barcode_layout): array
    {
        $barcode_images = [];
        $part_images = [];
        foreach ((new \DOMXPath($fragment->ownerDocument))->query('.//img', $fragment) as $img) {
            if (!$img instanceof \DOMElement) {
                continue;
            }
            if ($this->isPlaceholderImage($img, self::BARCODE_IMAGE_SRC_PREFIX, self::BARCODE_IMAGE_CLASS)) {
                $barcode_images[] = $img;
            } elseif ($this->isPlaceholderImage($img, self::PART_IMAGE_SRC_PREFIX, self::PART_IMAGE_CLASS)) {
                $part_images[] = $img;
            }
        }

        if ($barcode_images === [] && $part_images === []) {
            return [false, false];
        }

        foreach ($barcode_images as $img) {
            $this->placeBarcodeImage($img, $barcode_svg, $barcode_layout);
        }

        if ($part_images !== []) {
            $part_image = $this->partImageProvider->getImage($element);
            foreach ($part_images as $img) {
                $this->placePartImage($img, $part_image);
            }
        }

        return [true, $barcode_images !== []];
    }

    /**
     * CKEditor saves empty lines as paragraphs containing only a non-breaking space. In the editor, an empty line has no
     * width, so it fits next to a floating image even if there is only little space left. In dompdf the space has a width,
     * so the line may not fit there and is moved below the image (moving down the following text, too).
     * So we replace their content with a line break, which has no width but keeps the height of the line (like the empty
     * line in the editor). This is only done for lines with floating images, so other labels are not changed.
     * @return bool Whether the lines were changed
     */
    private function replaceEmptyLines(\DOMDocumentFragment $fragment): bool
    {
        $changed = false;
        foreach ((new \DOMXPath($fragment->ownerDocument))->query('.//p | .//h1 | .//h2 | .//h3 | .//h4 | .//h5 | .//h6', $fragment) as $block) {
            if (!$block instanceof \DOMElement || $block->getElementsByTagName('*')->length > 0
                || $block->textContent === '' || preg_replace('/[\s\x{00A0}]+/u', '', $block->textContent) !== '') {
                continue;
            }

            while ($block->firstChild !== null) {
                $block->removeChild($block->firstChild);
            }
            $block->appendChild($fragment->ownerDocument->createElement('br'));
            $changed = true;
        }

        return $changed;
    }

    /**
     * Returns the width (in mm) available for the lines, depending on the layout used for the barcode.
     * @param  array{width: float, height: float}|null  $barcode_layout
     */
    private function getAvailableWidth(LabelOptions $options, bool $barcode_placed, ?array $barcode_layout): float
    {
        $content_width = max($options->getWidth() - 2 * self::PAGE_MARGIN_X, 1.0);
        $type = $options->getBarcodeType();

        //The lines use the whole label, if there is no barcode layout
        if ($type === BarcodeType::NONE || $barcode_placed || $type->is1D()) {
            return $content_width;
        }

        //Otherwise the lines are next to the 2D barcode
        if ($options->getBarcodeSize() !== null && $barcode_layout !== null) {
            return max($content_width - $barcode_layout['width'] - self::SIDE_GAP, 1.0);
        }

        return $content_width * self::LEGACY_2D_LAYOUT['textWidth'];
    }

    /**
     * dompdf does not move floating elements down, if they do not fit next to the previous floating elements (it places
     * them on top of each other instead, see Dompdf\FrameReflower\Block::process_float()). Also, it ignores the "clear"
     * property of floating elements.
     * So we check this ourselves for consecutive floating images (like browsers do): A floating image, which does not fit
     * next to the previous ones, starts a new row below them. As dompdf places all consecutive floats at the same height,
     * the images of the new row are moved down with a top margin (so that the text still flows around the previous floats
     * like in browsers). If the height of the previous row is not known, a clearing element is inserted instead.
     * @param  float  $available_width The width available for the lines in mm
     * @return bool Whether the lines were changed
     */
    private function fitFloatingImages(\DOMDocumentFragment $fragment, float $available_width): bool
    {
        $available = $available_width * self::PX_PER_MM;
        //The gap between floating images and the text (see .image-style-align-left in label_style.css.twig)
        $gap = self::SIDE_GAP * self::PX_PER_MM;

        $changed = false;
        //The width used by the current row (in total and on each side), the height of the current row, and the offset of
        //the current row (all in px)
        $used = $used_left = $used_right = 0.0;
        $row_height = 0.0;
        $row_height_known = true;
        $offset = 0.0;
        //The images of the current group of consecutive floating images (with their position), or null if unknown
        $group = [];

        foreach (iterator_to_array($fragment->childNodes) as $node) {
            if ($node instanceof \DOMText && trim($node->textContent) === '') {
                continue;
            }

            $style = $node instanceof \DOMElement ? $this->getFloatStyle($node) : null;
            //Only consecutive floating images are checked, other content resets the rows
            if ($style === null) {
                if ($group !== null && $group !== [] && $node instanceof \DOMElement && $node->nodeName === 'p') {
                    $changed = $this->moveBelowFloatingImages($node, $group, $available) || $changed;
                }
                $used = $used_left = $used_right = $row_height = $offset = 0.0;
                $row_height_known = true;
                $group = [];
                continue;
            }

            $width = min($this->getElementWidth($node, $available) ?? 0.0, $available);
            $height = $this->getElementHeight($node, $width);

            //The space taken by the image including its gaps to the text, like in the label editor (see ckeditor.css):
            //Images wrapped left have the gap on their right side, images wrapped right on both sides. The gap after the
            //last image of a row may extend into the page margin, so the available width is extended by the gap.
            $needed = $width + ($style === 'align-left' ? $gap : 2 * $gap);

            if ($used > 0 && $used + $needed > $available + $gap + 0.5) {
                //Start a new row below the current one
                if ($row_height_known && $row_height > 0) {
                    $offset += $row_height;
                } else {
                    $clear = $fragment->ownerDocument->createElement('div');
                    $clear->setAttribute('class', 'partdb-float-break');
                    $clear->setAttribute('style', 'clear: both;');
                    $fragment->insertBefore($clear, $node);
                    $offset = 0.0;
                    //The position of the following images relative to the previous ones is not known anymore
                    $group = null;
                }
                $changed = true;
                $used = $used_left = $used_right = $row_height = 0.0;
                $row_height_known = true;
            }

            if ($offset > 0) {
                $node->setAttribute('style', trim($node->getAttribute('style').' margin-top: '.round($offset, 2).'px;'));
            }

            if ($group !== null) {
                if ($height === null) {
                    $group = null;
                } else {
                    //The horizontal space taken by the image (including its gaps) and its vertical position
                    $group[] = $style === 'align-left'
                        ? ['left' => $used_left, 'right' => $used_left + $needed, 'top' => $offset, 'bottom' => $offset + $height]
                        : ['left' => $available + $gap - $used_right - $needed, 'right' => $available + $gap - $used_right, 'top' => $offset, 'bottom' => $offset + $height, 'is_right' => true];
                }
            }

            if ($style === 'align-left') {
                $used_left += $needed;
            } else {
                $used_right += $needed;
            }
            $used += $needed;
            if ($height === null) {
                $row_height_known = false;
            } else {
                $row_height = max($row_height, $height);
            }
        }

        return $changed;
    }

    /**
     * dompdf moves text, which does not fit next to floating elements, down in steps of the line height (see the TODO in
     * Dompdf\FrameReflower\Text::layout_line()), so it often ends up lower than in browsers (and the label editor), which
     * place it directly below the floating elements. So if the given paragraph does not fit next to the given floating
     * images, we insert a spacer before it, so that it starts where browsers would place it.
     * @param  array<array{left: float, right: float, top: float, bottom: float, is_right?: bool}>  $images The positions
     *         (in px) of the floating images before the paragraph, relative to the position of the paragraph
     * @return bool Whether the lines were changed
     */
    private function moveBelowFloatingImages(\DOMElement $paragraph, array $images, float $available): bool
    {
        //The width the first line needs at least (about one character of the default font), and the height it takes
        $min_width = 7.2;
        $line_height = 12 * self::DEFAULT_LINE_HEIGHT;

        //The width available for text at the given height, next to the images, which are there at that height
        $width_at = static function (float $y) use ($images, $available): float {
            $left = 0.0;
            $right = $available;
            foreach ($images as $image) {
                if ($image['top'] <= $y && $y < $image['bottom']) {
                    if ($image['is_right'] ?? false) {
                        $right = min($right, $image['left']);
                    } else {
                        $left = max($left, $image['right']);
                    }
                }
            }
            return $right - $left;
        };

        //The first line is placed at the top, or directly below one of the images (where the available width changes)
        $candidates = [0.0];
        foreach ($images as $image) {
            $candidates[] = $image['bottom'];
            $candidates[] = $image['top'];
        }
        sort($candidates);

        foreach ($candidates as $y) {
            //The line must fit over its whole height
            $fits = $width_at($y) >= $min_width;
            foreach ($images as $image) {
                if ($image['top'] > $y && $image['top'] < $y + $line_height && $width_at($image['top']) < $min_width) {
                    $fits = false;
                }
            }

            if ($fits) {
                if ($y < 0.5) {
                    //The text fits next to the images, dompdf places it there, too
                    return false;
                }
                $spacer = $paragraph->ownerDocument->createElement('div');
                $spacer->setAttribute('class', 'partdb-float-spacer');
                //dompdf still treats a floating element as present at exactly its bottom edge (it compares with >=), so
                //the spacer must be a little higher
                $spacer->setAttribute('style', sprintf('height: %.2Fpx;', $y + 0.2));
                $paragraph->parentNode?->insertBefore($spacer, $paragraph);
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the height (in px) of a (floating) image or figure with the given width (in px), as defined by its style or
     * the aspect ratio of its width and height attributes, or null if it is unknown.
     */
    private function getElementHeight(\DOMElement $element, float $width): ?float
    {
        $img = $element->nodeName === 'img' ? $element : ($element->getElementsByTagName('img')->item(0));
        if ($img === null) {
            return null;
        }

        if ((float) $img->getAttribute('data-box-height') > 0) {
            return (float) $img->getAttribute('data-box-height');
        }

        if (preg_match('/(?:^|;)\s*height\s*:\s*([\d.]+)\s*(px|mm)/i', $img->getAttribute('style'), $matches) === 1) {
            return strtolower($matches[2]) === 'mm' ? (float) $matches[1] * self::PX_PER_MM : (float) $matches[1];
        }

        $attr_width = (float) $img->getAttribute('width');
        $attr_height = (float) $img->getAttribute('height');
        if ($attr_width > 0 && $attr_height > 0 && $width > 0) {
            return $width * $attr_height / $attr_width;
        }

        return null;
    }

    /**
     * Returns the CKEditor image style (align-left, align-right or side), if the given element is a floating image.
     */
    private function getFloatStyle(\DOMElement $element): ?string
    {
        if (!in_array($element->nodeName, ['figure', 'img'], true)) {
            return null;
        }

        return preg_match(self::FLOATING_IMAGE_REGEX, $element->getAttribute('class'), $matches) === 1 ? $matches[1] : null;
    }

    /**
     * Returns the width (in px) of a (floating) image or figure, as defined by its style or its width attribute, or null
     * if it is unknown.
     */
    private function getElementWidth(\DOMElement $element, float $available): ?float
    {
        $parse = static function (string $style) use ($available): ?float {
            if (preg_match('/(?:^|;)\s*width\s*:\s*([\d.]+)\s*(px|mm|%)/i', $style, $matches) !== 1) {
                return null;
            }
            return match (strtolower($matches[2])) {
                'mm' => (float) $matches[1] * self::PX_PER_MM,
                '%' => (float) $matches[1] / 100 * $available,
                default => (float) $matches[1],
            };
        };

        $img = $element->nodeName === 'img' ? $element : ($element->getElementsByTagName('img')->item(0));

        if ($img !== null && (float) $img->getAttribute('data-box-width') > 0) {
            return (float) $img->getAttribute('data-box-width');
        }

        return $parse($element->getAttribute('style'))
            ?? ($img !== null ? $parse($img->getAttribute('style')) : null)
            ?? ($img !== null && (float) $img->getAttribute('width') > 0 ? (float) $img->getAttribute('width') : null);
    }

    /**
     * Returns the figure wrapping the given image (CKEditor wraps images with a block style in a figure), or null.
     */
    private function getFigure(\DOMElement $img): ?\DOMElement
    {
        return $img->parentNode instanceof \DOMElement && $img->parentNode->nodeName === 'figure' ? $img->parentNode : null;
    }

    /**
     * Sets the style and classes of a replaced placeholder image and its figure, so that the figure has the given width
     * (then the CKEditor alignment styles work).
     */
    private function applyPlaceholderStyles(\DOMElement $img, string $class, string $img_style, string $figure_width): void
    {
        //The size is defined by us, so remove any size given in the editor
        $img->removeAttribute('width');
        $img->removeAttribute('height');
        $img->setAttribute('style', trim($this->removeSizeStyles($img->getAttribute('style')).' '.$img_style));
        $classes = array_diff(preg_split('/\s+/', $img->getAttribute('class'), -1, PREG_SPLIT_NO_EMPTY) ?: [], ['image_resized', $class]);
        $img->setAttribute('class', implode(' ', [...$classes, $class]));

        $figure = $this->getFigure($img);
        if ($figure !== null) {
            $figure->setAttribute('style', trim($this->removeSizeStyles($figure->getAttribute('style')).' width: '.$figure_width.';'));
            if (!str_contains($figure->getAttribute('class'), 'image_resized')) {
                $figure->setAttribute('class', trim($figure->getAttribute('class').' image_resized'));
            }
        }
    }

    /**
     * Replaces a barcode placeholder image with the barcode, or removes it if no barcode type is selected.
     * @param  array{width: float, height: float}|null  $layout
     */
    private function placeBarcodeImage(\DOMElement $img, ?string $barcode_svg, ?array $layout): void
    {
        if ($barcode_svg === null || $layout === null) {
            $node = $this->getFigure($img) ?? $img;
            $node->parentNode?->removeChild($node);
            return;
        }

        $img->setAttribute('src', 'data:image/svg+xml;base64,'.base64_encode($barcode_svg));
        $this->applyPlaceholderStyles($img, self::BARCODE_IMAGE_CLASS,
            sprintf('width: %smm; height: %smm;', $layout['width'], $layout['height']), $layout['width'].'mm');
    }

    /**
     * Replaces a part image placeholder with the image of the part: The placeholder keeps its size (as set in the editor),
     * and the image is centered in it and scaled so that its larger dimension fits. If there is no image, the placeholder
     * becomes an empty box of its size.
     * @param  array{uri: string, width: int, height: int}|null  $image
     */
    private function placePartImage(\DOMElement $img, ?array $image): void
    {
        [$box_width, $box_height] = $this->getPlaceholderSize($img);

        if ($image === null) {
            $img->setAttribute('src', self::EMPTY_IMAGE);
            $style = sprintf('width: %.2Fpx; height: %.2Fpx;', $box_width, $box_height);
        } else {
            //dompdf does not support object-fit: contain, so we scale the image ourselves and center it with padding
            $scale = min($box_width / $image['width'], $box_height / $image['height']);
            $width = $image['width'] * $scale;
            $height = $image['height'] * $scale;
            $img->setAttribute('src', $image['uri']);
            $style = sprintf('width: %.2Fpx; height: %.2Fpx; padding: %.2Fpx %.2Fpx;',
                $width, $height, ($box_height - $height) / 2, ($box_width - $width) / 2);
        }

        $this->applyPlaceholderStyles($img, self::PART_IMAGE_CLASS, $style, sprintf('%.2Fpx', $box_width));
        //The style contains the size of the fitted image, so remember the size of the box (see fitFloatingImages())
        $img->setAttribute('data-box-width', sprintf('%.2F', $box_width));
        $img->setAttribute('data-box-height', sprintf('%.2F', $box_height));
    }

    /**
     * Determines the size (in px) of a placeholder image: The width from the resized image or its figure (as set by
     * the resize handles of the editor, in px), or from the width attribute. The height keeps the aspect ratio of the
     * width and height attributes (or is set explicitly via the style).
     * @return array{float, float}
     */
    private function getPlaceholderSize(\DOMElement $img): array
    {
        $px = static function (string $style, string $property): ?float {
            return preg_match('/(?:^|;)\s*'.$property.'\s*:\s*([\d.]+)px/i', $style, $matches) === 1 ? (float) $matches[1] : null;
        };

        $attr_width = (float) $img->getAttribute('width') ?: self::PART_IMAGE_DEFAULT_SIZE;
        $attr_height = (float) $img->getAttribute('height') ?: $attr_width;

        $figure = $this->getFigure($img);
        $width = $px($img->getAttribute('style'), 'width')
            ?? ($figure !== null ? $px($figure->getAttribute('style'), 'width') : null)
            ?? $attr_width;
        $height = $px($img->getAttribute('style'), 'height') ?? $width * $attr_height / $attr_width;

        return [$width, $height];
    }

    /**
     * Removes all width, height and aspect-ratio declarations from the given CSS style string.
     */
    private function removeSizeStyles(string $style): string
    {
        $declarations = array_filter(array_map('trim', explode(';', $style)),
            static fn(string $declaration): bool => $declaration !== ''
                && preg_match('/^(width|height|aspect-ratio|min-width|max-width|min-height|max-height)\s*:/i', $declaration) !== 1
        );

        return $declarations === [] ? '' : implode('; ', $declarations).';';
    }

    private function getPDFTitle(LabelOptions $options, object $element): string
    {
        if ($element instanceof NamedElementInterface) {
            return $this->elementTypeNameGenerator->getTypeNameCombination($element, false);
        }

        return 'Part-DB label';
    }
}
