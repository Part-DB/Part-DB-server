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


namespace App\Services\InfoProviderSystem;

use App\Entity\Parts\Part;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\InfoProviderSystem\Providers\CanopyProvider;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use App\Services\InfoProviderSystem\Providers\URLHandlerInfoProviderInterface;
use App\Settings\InfoProviderSystem\CanopySettings;
use App\Settings\InfoProviderSystem\InfoProviderGeneralSettings;

/**
 * Decides which info provider can supply the data of a part, that has no info provider data yet, when its info page
 * is opened ("fetch on view", see ProviderOnViewFetcher).
 *
 * A part is recognized by its orderdetails, in this order:
 *  1. The product URL of an orderdetail is a product page of the provider. The URL then gives the ID of the product.
 *  2. The supplier of an orderdetail is named like the provider and the orderdetail has a supplier part number.
 *     The product then has to be searched at the provider, and only a result with exactly this number is accepted.
 *
 * Nothing in here contacts a provider, so it can be used on every page view.
 *
 * @see \App\Tests\Services\InfoProviderSystem\ProviderOnViewMatcherTest
 */
final class ProviderOnViewMatcher
{
    public function __construct(
        private readonly InfoProviderGeneralSettings $settings,
        private readonly CanopySettings $canopySettings,
        private readonly ProviderRegistry $providerRegistry,
    ) {
    }

    /**
     * Returns the providers which fetch data when a part is viewed, in the order they were configured in.
     * Providers which are not active (or do not exist) are ignored.
     * @return array<string, InfoProviderInterface> The providers indexed by their keys
     */
    public function getEnabledProviders(): array
    {
        $keys = $this->settings->fetchOnViewProviders;
        //The Canopy provider has its own switch, which exists longer than the general setting
        if ($this->canopySettings->fetchOnView) {
            $keys[] = CanopyProvider::PROVIDER_KEY;
        }

        if ($keys === []) {
            return [];
        }

        $active = $this->providerRegistry->getActiveProviders();

        $providers = [];
        foreach ($keys as $key) {
            if (is_string($key) && isset($active[$key])) {
                $providers[$key] = $active[$key];
            }
        }

        return $providers;
    }

    /**
     * Returns the maximum number of parts, which may be looked up at the given provider within 24 hours because
     * their page was viewed. 0 means no limit.
     */
    public function getDailyLimit(string $provider_key): int
    {
        if ($provider_key === CanopyProvider::PROVIDER_KEY) {
            return $this->canopySettings->fetchOnViewDailyLimit;
        }

        return $this->settings->fetchOnViewDailyLimit;
    }

    /**
     * Returns the ways the given part can be looked up at the enabled providers, best one first. This is empty for
     * parts which already have info provider data, as only parts nobody looked up yet are filled when viewed.
     * @return OnViewMatch[]
     */
    public function findMatches(Part $part): array
    {
        //A provider reference means the part data already came from an info provider
        if ($part->getProviderReference()->isProviderCreated()) {
            return [];
        }

        $providers = $this->getEnabledProviders();
        if ($providers === []) {
            return [];
        }

        $by_url = [];
        $by_supplier = [];

        foreach ($part->getOrderdetails() as $orderdetail) {
            //This includes URLs generated from the supplier's product URL template and the supplier part number
            $url = $orderdetail->getSupplierProductUrl();
            $supplier_name = self::normalizeName((string) $orderdetail->getSupplier()?->getName());
            $supplier_part_nr = trim($orderdetail->getSupplierPartNr());

            foreach ($providers as $provider) {
                $id = $this->getProviderIdFromURL($provider, $url);
                if ($id !== null) {
                    $by_url[] = new OnViewMatch($provider, $orderdetail, providerId: $id);
                    continue;
                }

                if ($supplier_part_nr !== '' && $supplier_name !== ''
                    && $supplier_name === self::normalizeName($provider->getProviderInfo()->name)) {
                    $by_supplier[] = new OnViewMatch($provider, $orderdetail, supplierPartNr: $supplier_part_nr);
                }
            }
        }

        return [...$by_url, ...$by_supplier];
    }

    /**
     * Returns the ID the given provider uses for the product behind the given URL, or null if the URL is not a
     * product page of the provider.
     */
    public function getProviderIdFromURL(InfoProviderInterface $provider, ?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        //Canopy does not handle URLs itself: its IDs are the ASINs, which are part of the Amazon product page URLs
        if ($provider->getProviderInfo()->key === CanopyProvider::PROVIDER_KEY) {
            //Canopy is queried for the configured marketplace only, an ASIN of another one would give wrong data
            if (!self::isHostOfDomain($host, $this->canopySettings->getRealDomain())) {
                return null;
            }

            $path = (string) parse_url($url, PHP_URL_PATH);
            if (preg_match('#/(?:dp|gp/product|gp/aw/d|exec/obidos/ASIN)/([A-Z0-9]{10})(?:[/?]|$)#', $path, $matches) === 1) {
                return $matches[1];
            }

            return null;
        }

        if (!$provider instanceof URLHandlerInfoProviderInterface) {
            return null;
        }

        foreach ($provider->getHandledDomains() as $domain) {
            if (self::isHostOfDomain($host, (string) $domain)) {
                $id = $provider->getIDFromURL($url);

                return $id === null || trim($id) === '' ? null : $id;
            }
        }

        return null;
    }

    /**
     * Picks the search result which is the product with the given supplier part number, or returns null if the
     * results do not contain exactly this product. A result is never chosen because it is merely similar: the data
     * is added to the part without anybody reviewing it, so no data is better than the data of the wrong product.
     *
     * A result is the product, if its provider ID, its manufacturer part number or one of its order numbers is the
     * supplier part number (see numbersEqual()). If this is true for multiple results, they must all carry the same
     * manufacturer part number (like the packaging variants of one product do), and then the first one is taken.
     *
     * @param  SearchResultDTO[]  $results The results of searching the provider for the supplier part number
     */
    public function pickExactResult(InfoProviderInterface $provider, string $supplier_part_nr, array $results): ?SearchResultDTO
    {
        $info = $provider->getProviderInfo();
        $vendor_names = [$info->key, $info->name];

        /** @var array<string, SearchResultDTO> $exact */
        $exact = [];
        foreach ($results as $result) {
            if (!$result instanceof SearchResultDTO || $result->provider_key !== $info->key) {
                continue;
            }

            $numbers = [$result->provider_id, $result->mpn];
            if ($result instanceof PartDetailDTO) {
                foreach ($result->vendor_infos ?? [] as $vendor_info) {
                    $numbers[] = $vendor_info->order_number;
                }
            }

            foreach ($numbers as $number) {
                if ($number !== null && self::numbersEqual($supplier_part_nr, $number, $vendor_names)) {
                    $exact[$result->provider_id] ??= $result;
                    break;
                }
            }
        }

        if (count($exact) <= 1) {
            return array_values($exact)[0] ?? null;
        }

        //Multiple different products claim this number. That is only fine if they are the same article.
        $mpns = [];
        foreach ($exact as $result) {
            $mpns[mb_strtolower(trim((string) $result->mpn))] = true;
        }
        if (count($mpns) !== 1 || isset($mpns[''])) {
            return null;
        }

        return array_values($exact)[0];
    }

    /**
     * Checks if the two given part numbers are the same one. They are compared case-insensitively, and a vendor
     * prefix in front of a numeric part number is ignored if the other number has none: "4062" is the same as
     * "ADA4062" (a prefix taken from the name of the vendor) or "DEV-04062" (a prefix set apart by a separator).
     * Two numbers with different prefixes (DEV-123 and PRT-123) are different.
     *
     * @param  string[]  $vendor_names The names of the vendor, a prefix has to be the beginning of one of them if
     * it is not set apart from the number
     */
    public static function numbersEqual(string $a, string $b, array $vendor_names = []): bool
    {
        $a = trim($a);
        $b = trim($b);
        if ($a === '' || $b === '') {
            return false;
        }

        if (mb_strtolower($a) === mb_strtolower($b)) {
            return true;
        }

        $bare_a = self::bareNumber($a);
        $bare_b = self::bareNumber($b);
        if ($bare_a !== null && $bare_b !== null) {
            return $bare_a === $bare_b;
        }

        //Otherwise exactly one of them must be a bare number, and the other one this number with a prefix
        if ($bare_a !== null) {
            return self::stripVendorPrefix($b, $vendor_names) === $bare_a;
        }
        if ($bare_b !== null) {
            return self::stripVendorPrefix($a, $vendor_names) === $bare_b;
        }

        return false;
    }

    /**
     * Returns the number without leading zeros (and without a leading #), if the given string is just a number.
     */
    private static function bareNumber(string $value): ?string
    {
        if (preg_match('/^#?\s*0*(\d+)$/', $value, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Returns the number (without leading zeros) of a part number which consists of a vendor prefix and a number,
     * or null if the given part number does not look like that.
     * @param  string[]  $vendor_names
     */
    private static function stripVendorPrefix(string $value, array $vendor_names): ?string
    {
        if (preg_match('/^([A-Za-z]{2,})([\s\-_#:.]*)0*(\d+)$/', $value, $matches) !== 1) {
            return null;
        }

        //A prefix followed by a separator is clearly no part of the number itself
        if ($matches[2] !== '') {
            return $matches[3];
        }

        //Without one, this could as well be a type designation (like KM100), so the prefix has to be the vendor
        $prefix = strtolower($matches[1]);
        foreach ($vendor_names as $vendor_name) {
            if (str_starts_with(self::normalizeName($vendor_name), $prefix)) {
                return $matches[3];
            }
        }

        return null;
    }

    /**
     * Reduces a supplier or provider name to its letters and digits in lower case, so "Bambu Lab", "BambuLab" and
     * "bambu-lab" are the same name.
     */
    public static function normalizeName(string $name): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($name)) ?? '';
    }

    private static function isHostOfDomain(string $host, string $domain): bool
    {
        $host = strtolower($host);
        $domain = strtolower($domain);

        return $domain !== '' && ($host === $domain || str_ends_with($host, '.'.$domain));
    }
}
