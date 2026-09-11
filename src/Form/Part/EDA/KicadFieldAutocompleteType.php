<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Form\Part\EDA;

use App\Form\Type\StaticFileAutocompleteType;
use App\Settings\MiscSettings\KiCadEDASettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This is a specialized version of the StaticFileAutocompleteType, which loads the different types of Kicad lists.
 */
class KicadFieldAutocompleteType extends AbstractType
{
    public const TYPE_FOOTPRINT = 'footprint';
    public const TYPE_SYMBOL = 'symbol';

    //Do not use a leading slash here! otherwise it will not work under prefixed reverse proxies
    public const FOOTPRINT_PATH = 'kicad/footprints.txt';
    public const SYMBOL_PATH = 'kicad/symbols.txt';
    public const CUSTOM_FOOTPRINT_PATH = 'kicad/footprints_custom.txt';
    public const CUSTOM_SYMBOL_PATH = 'kicad/symbols_custom.txt';

    public function __construct(
        private readonly KiCadEDASettings $kiCadEDASettings,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('type');
        $resolver->setAllowedValues('type', [self::TYPE_SYMBOL, self::TYPE_FOOTPRINT]);

        $resolver->setDefaults([
            'file' => fn(Options $options) => match ($options['type']) {
                self::TYPE_FOOTPRINT => $this->kiCadEDASettings->useCustomList ? self::CUSTOM_FOOTPRINT_PATH : self::FOOTPRINT_PATH,
                self::TYPE_SYMBOL => $this->kiCadEDASettings->useCustomList ? self::CUSTOM_SYMBOL_PATH : self::SYMBOL_PATH,
                default => throw new \InvalidArgumentException('Invalid type'),
            }
        ]);
    }

    public function getParent(): string
    {
        return StaticFileAutocompleteType::class;
    }
}
