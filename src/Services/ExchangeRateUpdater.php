<?php

namespace App\Services;

use App\Entity\PriceInformations\Currency;
use Brick\Math\BigDecimal;
use Swap\Swap;

class ExchangeRateUpdater
{
    private string $base_currency;
    private Swap $swap;

    public function __construct(string $base_currency, Swap $swap)
    {
        $this->base_currency = $base_currency;
        $this->swap = $swap;
    }

    /**
     * Updates the exchange rate of the given currency using the globally configured providers.
     */
    public function update(Currency $currency): Currency
    {
        $rate = $this->swap->latest($currency->getIsoCode().'/'.$this->base_currency);
        $currency->setExchangeRate(BigDecimal::of($rate->getValue()));

        return $currency;
    }
}
