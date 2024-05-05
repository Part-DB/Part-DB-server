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


namespace App\Services\InfoProviderSystem\Providers;

use App\Settings\InfoProviderSystem\TMESettings;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TMEClient
{
    public const BASE_URI = 'https://api.tme.eu';

    public function __construct(private readonly HttpClientInterface $tmeClient, private readonly TMESettings $settings)
    {

    }

    public function makeRequest(string $action, array $parameters): ResponseInterface
    {
        $parameters['Token'] = $this->settings->apiToken;
        $parameters['ApiSignature'] = $this->getSignature($action, $parameters, $this->settings->apiSecret);

        return $this->tmeClient->request('POST', $this->getUrlForAction($action), [
            'body' => $parameters,
        ]);
    }

    public function isUsable(): bool
    {
        return !($this->settings->apiToken === '' || $this->settings->apiSecret === '');
    }


    /**
     * Generates the signature for the given action and parameters.
     * Taken from https://github.com/tme-dev/TME-API/blob/master/PHP/basic/using_curl.php
     */
    public function getSignature(string $action, array $parameters, string $appSecret): string
    {
        $parameters = $this->sortSignatureParams($parameters);

        $queryString = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        $signatureBase = strtoupper('POST') .
            '&' . rawurlencode($this->getUrlForAction($action)) . '&' . rawurlencode($queryString);

        return base64_encode(hash_hmac('sha1', $signatureBase, $appSecret, true));
    }

    private function getUrlForAction(string $action): string
    {
        return self::BASE_URI . '/' . $action . '.json';
    }

    private function sortSignatureParams(array $params): array
    {
        ksort($params);

        foreach ($params as &$value) {
            if (is_array($value)) {
                $value = $this->sortSignatureParams($value);
            }
        }

        return $params;
    }
}