<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\DataFixtures;

use App\Entity\PriceInformations\Currency;
use Brick\Math\BigDecimal;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CurrencyFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $currency1 = new Currency();
        $currency1->setName('US-Dollar');
        $currency1->setIsoCode('USD');
        $manager->persist($currency1);

        $currency2 = new Currency();
        $currency2->setName('Swiss Franc');
        $currency2->setIsoCode('CHF');
        $currency2->setExchangeRate(BigDecimal::of('0.91'));
        $manager->persist($currency2);

        $currency3 = new Currency();
        $currency3->setName('Great British Pound');
        $currency3->setIsoCode('GBP');
        $currency3->setExchangeRate(BigDecimal::of('0.78'));
        $manager->persist($currency3);

        $currency7 = new Currency();
        $currency7->setName('Test Currency with long name');
        $currency7->setIsoCode('CNY');
        $manager->persist($currency7);

        $manager->flush();


        //Ensure that currency 7 gets ID 7
        $manager->getRepository(Currency::class)->changeID($currency7, 7);
        $manager->flush();
    }
}
