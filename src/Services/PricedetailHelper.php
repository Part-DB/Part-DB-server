<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Services;

use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Pricedetail;
use Doctrine\ORM\PersistentCollection;
use Locale;

class PricedetailHelper
{
    protected $base_currency;
    protected $locale;

    public function __construct(string $base_currency)
    {
        $this->base_currency = $base_currency;
        $this->locale = Locale::getDefault();
    }

    /**
     * Determines the highest amount, for which you get additional discount.
     * This function determines the highest min_discount_quantity for the given part.
     */
    public function getMaxDiscountAmount(Part $part): ?float
    {
        $orderdetails = $part->getOrderdetails(true);

        $max = 0;

        foreach ($orderdetails as $orderdetail) {
            $pricedetails = $orderdetail->getPricedetails();
            //The orderdetail must have pricedetails, otherwise this will not work!
            if (0 === \count($pricedetails)) {
                continue;
            }

            if ($pricedetails instanceof PersistentCollection) {
                /* Pricedetails in orderdetails are ordered by min discount quantity,
                    so our first object is our min order amount for the current orderdetail */
                $max_amount = $pricedetails->last()->getMinDiscountQuantity();
            } else {
                // We have to sort the pricedetails manually
                $array = $pricedetails->map(
                    function (Pricedetail $pricedetail) {
                        return $pricedetail->getMinDiscountQuantity();
                    }
                )->toArray();
                sort($array);
                $max_amount = end($array);
            }

            if ($max_amount > $max) {
                $max = $max_amount;
            }
        }

        if ($max > 0.0) {
            return $max;
        }

        return null;
    }

    /**
     * Determines the minimum amount of the part that can be ordered.
     *
     * @param Part $part the part for which the minimum order amount should be determined
     *
     * @return float
     */
    public function getMinOrderAmount(Part $part): ?float
    {
        $orderdetails = $part->getOrderdetails(true);

        $min = INF;

        foreach ($orderdetails as $orderdetail) {
            $pricedetails = $orderdetail->getPricedetails();
            //The orderdetail must have pricedetails, otherwise this will not work!
            if (0 === \count($pricedetails)) {
                continue;
            }

            /* Pricedetails in orderdetails are ordered by min discount quantity,
                so our first object is our min order amount for the current orderdetail */
            $min_amount = $pricedetails[0]->getMinDiscountQuantity();

            if ($min_amount < $min) {
                $min = $min_amount;
            }
        }

        if ($min < INF) {
            return $min;
        }

        return null;
    }

    /**
     * Calculates the average price of a part, when ordering the amount $amount.
     *
     * @param Part          $part     the part for which the average price should be calculated
     * @param float         $amount   The order amount for which the average price should be calculated.
     *                                If set to null, the mininmum order amount for the part is used.
     * @param Currency|null $currency The currency in which the average price should be calculated
     *
     * @return string|null The Average price as bcmath string. Returns null, if it was not possible to calculate the
     *                     price for the given
     */
    public function calculateAvgPrice(Part $part, ?float $amount = null, ?Currency $currency = null): ?string
    {
        if (null === $amount) {
            $amount = $this->getMinOrderAmount($part);
        }

        if (null === $amount) {
            return null;
        }

        $orderdetails = $part->getOrderdetails(true);

        $avg = '0';
        $count = 0;

        //Find the price for the amount, for the given
        foreach ($orderdetails as $orderdetail) {
            $pricedetail = $orderdetail->findPriceForQty($amount);

            //When we dont have informations about this amount, ignore it
            if (null === $pricedetail) {
                continue;
            }

            $avg = bcadd($avg, $this->convertMoneyToCurrency($pricedetail->getPricePerUnit(), $pricedetail->getCurrency(), $currency), Pricedetail::PRICE_PRECISION);
            ++$count;
        }

        if (0 === $count) {
            return null;
        }

        return bcdiv($avg, (string) $count, Pricedetail::PRICE_PRECISION);
    }

    /**
     * Converts the given value in origin currency to the choosen target currency.
     *
     * @param Currency|null $originCurrency The currency the $value is given in.
     *                                      Set to null, to use global base currency.
     * @param Currency|null $targetCurrency The target currency, to which $value should be converted.
     *                                      Set to null, to use global base currency.
     *
     * @return string|null The value in $targetCurrency given as bcmath string.
     *                     Returns null, if it was not possible to convert between both values (e.g. when the exchange rates are missing)
     */
    public function convertMoneyToCurrency($value, ?Currency $originCurrency = null, ?Currency $targetCurrency = null): ?string
    {
        //Skip conversion, if both currencies are same
        if ($originCurrency === $targetCurrency) {
            return $value;
        }

        $value = (string) $value;

        //Convert value to base currency
        $val_base = $value;
        if (null !== $originCurrency) {
            //Without an exchange rate we can not calculate the exchange rate
            if (0.0 === (float) $originCurrency->getExchangeRate()) {
                return null;
            }

            $val_base = bcmul($value, $originCurrency->getExchangeRate(), Pricedetail::PRICE_PRECISION);
        }

        //Convert value in base currency to target currency
        $val_target = $val_base;
        if (null !== $targetCurrency) {
            //Without an exchange rate we can not calculate the exchange rate
            if (null === $targetCurrency->getExchangeRate()) {
                return null;
            }

            $val_target = bcmul($val_base, $targetCurrency->getInverseExchangeRate(), Pricedetail::PRICE_PRECISION);
        }

        return $val_target;
    }
}
