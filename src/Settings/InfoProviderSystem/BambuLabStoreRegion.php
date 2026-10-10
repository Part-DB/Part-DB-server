<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Settings\InfoProviderSystem;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The regional Bambu Lab stores. The value is the region code the store API expects in its region header.
 */
enum BambuLabStoreRegion: string implements TranslatableInterface
{
    case US = 'US';
    case CA = 'CA';
    case MX = 'MX';
    case EU = 'EU';
    case UK = 'UK';
    case AU = 'AU';
    case JP = 'JP';
    case KR = 'KR';
    case GLOBAL = 'GLOBAL';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->getDomain() . ' (' . $this->getCurrency() . ')';
    }

    /**
     * The domain of the store website of this region.
     */
    public function getDomain(): string
    {
        return match ($this) {
            self::GLOBAL => 'store.bambulab.com',
            default => strtolower($this->value) . '.store.bambulab.com',
        };
    }

    /**
     * The base URL of the API the store website of this region uses.
     */
    public function getApiBaseUrl(): string
    {
        return match ($this) {
            self::US, self::CA, self::MX => 'https://na-store-api.bambulab.com',
            self::EU, self::UK => 'https://eu-store-api.bambulab.com',
            default => 'https://ap-store-api.bambulab.com',
        };
    }

    /**
     * The ISO code of the currency the prices of this store are given in.
     */
    public function getCurrency(): string
    {
        return match ($this) {
            self::US, self::GLOBAL => 'USD',
            self::CA => 'CAD',
            self::MX => 'MXN',
            self::EU => 'EUR',
            self::UK => 'GBP',
            self::AU => 'AUD',
            self::JP => 'JPY',
            self::KR => 'KRW',
        };
    }

    /**
     * The locale which is requested from the API. The japanese and korean stores are only available in their language.
     */
    public function getLocale(): string
    {
        return match ($this) {
            self::JP => 'ja-JP',
            self::KR => 'ko-KR',
            default => 'en-US',
        };
    }

    /**
     * Whether the prices shown in this store include VAT / sales tax, null if unknown.
     */
    public function pricesIncludeVAT(): ?bool
    {
        return match ($this) {
            self::US, self::CA => false,
            self::GLOBAL => null,
            default => true,
        };
    }
}
