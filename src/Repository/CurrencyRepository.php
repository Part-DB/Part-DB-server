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


namespace App\Repository;

use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\PriceInformations\Currency;
use Symfony\Component\Intl\Currencies;

/**
 * @extends StructuralDBElementRepository<Currency>
 */
class CurrencyRepository extends StructuralDBElementRepository
{
    /**
     * Finds or create a currency with the given ISO code.
     * @param  string  $iso_code
     * @return Currency
     */
    public function findOrCreateByISOCode(string $iso_code): Currency
    {
        //Normalize ISO code
        $iso_code = strtoupper($iso_code);

        //Try to find currency
        $currency = $this->findOneBy(['iso_code' => $iso_code]);
        if ($currency !== null) {
            return $currency;
        }

        //Create currency if it does not exist
        $name = Currencies::getName($iso_code);

        $currency = $this->findOrCreateForInfoProvider($name);
        $currency->setIsoCode($iso_code);

        return $currency;
    }
}