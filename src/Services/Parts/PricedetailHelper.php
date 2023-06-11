<?php
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

declare(strict_types=1);

namespace App\Services\Parts;

use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Pricedetail;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\ORM\PersistentCollection;
use Locale;

use function count;

class PricedetailHelper
{
    protected string $locale;

    public function __construct(protected string $base_currency)
    {
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
            if (0 === count($pricedetails)) {
                continue;
            }

            if ($pricedetails instanceof PersistentCollection) {
                /* Pricedetails in orderdetails are ordered by min discount quantity,
                    so our first object is our min order amount for the current orderdetail */
                $max_amount = $pricedetails->last()->getMinDiscountQuantity();
            } else {
                // We have to sort the pricedetails manually
                $array = $pricedetails->map(
                    static fn(Pricedetail $pricedetail) => $pricedetail->getMinDiscountQuantity()
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
            if (0 === count($pricedetails)) {
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
     * @param  Part  $part  the part for which the average price should be calculated
     * @param  float|null  $amount  The order amount for which the average price should be calculated.
     *                                If set to null, the mininmum order amount for the part is used.
     * @param  Currency|null  $currency  The currency in which the average price should be calculated
     *
     * @return BigDecimal|null The Average price as bcmath string. Returns null, if it was not possible to calculate the
     *                         price for the given
     */
    public function calculateAvgPrice(Part $part, ?float $amount = null, ?Currency $currency = null): ?BigDecimal
    {
        if (null === $amount) {
            $amount = $this->getMinOrderAmount($part);
        }

        if (null === $amount) {
            return null;
        }

        $orderdetails = $part->getOrderdetails(true);

        $avg = BigDecimal::zero();
        $count = 0;

        //Find the price for the amount, for the given
        foreach ($orderdetails as $orderdetail) {
            $pricedetail = $orderdetail->findPriceForQty($amount);

            //When we don't have information about this amount, ignore it
            if (!$pricedetail instanceof Pricedetail) {
                continue;
            }

            $converted = $this->convertMoneyToCurrency($pricedetail->getPricePerUnit(), $pricedetail->getCurrency(), $currency);
            //Ignore price information that can not be converted to base currency.
            if ($converted instanceof BigDecimal) {
                $avg = $avg->plus($converted);
                ++$count;
            }
        }

        if (0 === $count) {
            return null;
        }

        return $avg->dividedBy($count, Pricedetail::PRICE_PRECISION, RoundingMode::HALF_UP);
    }

    /**
     * Converts the given value in origin currency to the choosen target currency.
     *
     * @param Currency|null $originCurrency The currency the $value is given in.
     *                                      Set to null, to use global base currency.
     * @param Currency|null $targetCurrency The target currency, to which $value should be converted.
     *                                      Set to null, to use global base currency.
     *
     * @return BigDecimal|null The value in $targetCurrency given as bcmath string.
     *                         Returns null, if it was not possible to convert between both values (e.g. when the exchange rates are missing)
     */
    public function convertMoneyToCurrency(BigDecimal $value, ?Currency $originCurrency = null, ?Currency $targetCurrency = null): ?BigDecimal
    {
        //Skip conversion, if both currencies are same
        if ($originCurrency === $targetCurrency) {
            return $value;
        }

        $val_base = $value;
        //Convert value to base currency
        if ($originCurrency instanceof Currency) {
            //Without an exchange rate we can not calculate the exchange rate
            if (!$originCurrency->getExchangeRate() instanceof BigDecimal || $originCurrency->getExchangeRate()->isZero()) {
                return null;
            }

            $val_base = $value->multipliedBy($originCurrency->getExchangeRate());
        }

        $val_target = $val_base;
        //Convert value in base currency to target currency
        if ($targetCurrency instanceof Currency) {
            //Without an exchange rate we can not calculate the exchange rate
            if (!$targetCurrency->getExchangeRate() instanceof BigDecimal) {
                return null;
            }

            $val_target = $val_base->multipliedBy($targetCurrency->getInverseExchangeRate());
        }

        return $val_target->toScale(Pricedetail::PRICE_PRECISION, RoundingMode::HALF_UP);
    }
}
