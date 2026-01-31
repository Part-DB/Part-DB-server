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

enum ConradShopIDs: string implements TranslatableInterface
{
    case COM_B2B = 'HP_COM_B2B';
    case DE_B2B = 'CQ_DE_B2B';
    case AT_B2C = 'CQ_AT_B2C';
    case CH_B2C_DE = 'CQ_CH_B2C_DE';
    case CH_B2C_FR = 'CQ_CH_B2C_FR';
    case SE_B2B = 'HP_SE_B2B';
    case HU_B2C = 'CQ_HU_B2C';
    case CZ_B2B = 'HP_CZ_B2B';
    case SI_B2B = 'HP_SI_B2B';
    case SK_B2B = 'HP_SK_B2B';
    case BE_B2B = 'HP_BE_B2B';
    case DE_B2C = 'CQ_DE_B2C';
    case PL_B2B = 'HP_PL_B2B';
    case NL_B2B = 'CQ_NL_B2B';
    case DK_B2B = 'HP_DK_B2B';
    case IT_B2B = 'HP_IT_B2B';
    case NL_B2C = 'CQ_NL_B2C';
    case FR_B2B = 'HP_FR_B2B';
    case AT_B2B = 'CQ_AT_B2B';
    case HR_B2B = 'HP_HR_B2B';


    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return match ($this) {
            self::DE_B2B => "conrad.de (B2B)",
            self::AT_B2C => "conrad.at (B2C)",
            self::CH_B2C_DE => "conrad.ch DE (B2C)",
            self::CH_B2C_FR => "conrad.ch FR (B2C)",
            self::SE_B2B => "conrad.se (B2B)",
            self::HU_B2C => "conrad.hu (B2C)",
            self::CZ_B2B => "conrad.cz (B2B)",
            self::SI_B2B => "conrad.si (B2B)",
            self::SK_B2B => "conrad.sk (B2B)",
            self::BE_B2B => "conrad.be (B2B)",
            self::DE_B2C => "conrad.de (B2C)",
            self::PL_B2B => "conrad.pl (B2B)",
            self::NL_B2B => "conrad.nl (B2B)",
            self::DK_B2B => "conradelektronik.dk (B2B)",
            self::IT_B2B => "conrad.it (B2B)",
            self::NL_B2C => "conrad.nl (B2C)",
            self::FR_B2B => "conrad.fr (B2B)",
            self::COM_B2B => "conrad.com (B2B)",
            self::AT_B2B => "conrad.at (B2B)",
            self::HR_B2B => "conrad.hr (B2B)",
        };
    }

    public function getDomain(): string
    {
        if ($this === self::DK_B2B) {
            return 'conradelektronik.dk';
        }

        return 'conrad.' . $this->getDomainEnd();
    }

    /**
     * Retrieves the API root URL for this shop ID. e.g. https://api.conrad.de
     * @return string
     */
    public function getAPIRoot(): string
    {
        return 'https://api.' . $this->getDomain();
    }

    /**
     * Returns the shop ID value used in the API requests. e.g. 'CQ_DE_B2B'
     * @return string
     */
    public function getShopID(): string
    {
        return $this->value;
    }

    public function getDomainEnd(): string
    {
        return match ($this) {
            self::DE_B2B, self::DE_B2C => 'de',
            self::AT_B2B, self::AT_B2C => 'at',
            self::CH_B2C_DE => 'ch', self::CH_B2C_FR => 'ch',
            self::SE_B2B => 'se',
            self::HU_B2C => 'hu',
            self::CZ_B2B => 'cz',
            self::SI_B2B => 'si',
            self::SK_B2B => 'sk',
            self::BE_B2B => 'be',
            self::PL_B2B => 'pl',
            self::NL_B2B, self::NL_B2C => 'nl',
            self::DK_B2B => 'dk',
            self::IT_B2B => 'it',
            self::FR_B2B => 'fr',
            self::COM_B2B => 'com',
            self::HR_B2B => 'hr',
        };
    }

    public function getLanguage(): string
    {
        return match ($this) {
            self::DE_B2B, self::DE_B2C, self::AT_B2B, self::AT_B2C => 'de',
            self::CH_B2C_DE => 'de', self::CH_B2C_FR => 'fr',
            self::SE_B2B => 'sv',
            self::HU_B2C => 'hu',
            self::CZ_B2B => 'cs',
            self::SI_B2B => 'sl',
            self::SK_B2B => 'sk',
            self::BE_B2B => 'nl',
            self::PL_B2B => 'pl',
            self::NL_B2B, self::NL_B2C => 'nl',
            self::DK_B2B => 'da',
            self::IT_B2B => 'it',
            self::FR_B2B => 'fr',
            self::COM_B2B => 'en',
            self::HR_B2B => 'hr',
        };
    }

    /**
     * Retrieves the customer type for this shop ID. e.g. 'b2b' or 'b2c'
     * @return string 'b2b' or 'b2c'
     */
    public function getCustomerType(): string
    {
        return match ($this) {
            self::DE_B2B, self::AT_B2B, self::SE_B2B, self::CZ_B2B, self::SI_B2B,
            self::SK_B2B, self::BE_B2B, self::PL_B2B, self::NL_B2B, self::DK_B2B,
            self::IT_B2B, self::FR_B2B, self::COM_B2B, self::HR_B2B => 'b2b',
            self::DE_B2C, self::AT_B2C, self::CH_B2C_DE, self::CH_B2C_FR, self::HU_B2C, self::NL_B2C => 'b2c',
        };
    }
}
