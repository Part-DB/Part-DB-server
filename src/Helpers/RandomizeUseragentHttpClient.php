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


namespace App\Helpers;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * HttpClient wrapper that randomizes the user agent for each request, to make it harder for servers to detect and block us.
 * When we get a 503, 403 or 429, we assume that the server is blocking us and try again with a different user agent, until we run out of retries.
 */
final class RandomizeUseragentHttpClient implements HttpClientInterface
{
    public const USER_AGENTS = [
        "Mozilla/5.0 (Windows; U; Windows NT 10.0; Win64; x64) AppleWebKit/534.16 (KHTML, like Gecko) Chrome/52.0.1359.302 Safari/600.6 Edge/15.25690",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36 Edge/16.16299",
        "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 8_8_3) Gecko/20100101 Firefox/51.6",
        "Mozilla/5.0 (Android; Android 4.4.4; E:number:20-23:00 Build/24.0.B.1.34) AppleWebKit/603.18 (KHTML, like Gecko)  Chrome/47.0.1559.384 Mobile Safari/600.5",
        "Mozilla/5.0 (compatible; MSIE 9.0; Windows; Windows NT 6.3; WOW64 Trident/5.0)",
        "Mozilla/5.0 (Windows; Windows NT 6.0; Win64; x64) AppleWebKit/602.21 (KHTML, like Gecko) Chrome/51.0.3187.154 Safari/536",
        "Mozilla/5.0 (iPhone; CPU iPhone OS 9_4_2; like Mac OS X) AppleWebKit/537.24 (KHTML, like Gecko)  Chrome/51.0.2432.275 Mobile Safari/535.6",
        "Mozilla/5.0 (U; Linux i680 ) Gecko/20100101 Firefox/57.5",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 8_8_6; en-US) Gecko/20100101 Firefox/53.9",
        "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 8_6_7) AppleWebKit/534.46 (KHTML, like Gecko) Chrome/55.0.3276.345 Safari/535",
        "Mozilla/5.0 (Windows; Windows NT 10.5;) AppleWebKit/535.42 (KHTML, like Gecko) Chrome/53.0.1176.353 Safari/534.0 Edge/11.95743",
        "Mozilla/5.0 (Linux; Android 5.1.1; MOTO G Build/LPH223) AppleWebKit/600.27 (KHTML, like Gecko)  Chrome/47.0.1604.204 Mobile Safari/535.1",
        "Mozilla/5.0 (iPod; CPU iPod OS 7_4_8; like Mac OS X) AppleWebKit/534.17 (KHTML, like Gecko)  Chrome/50.0.1632.146 Mobile Safari/600.4",
        "Mozilla/5.0 (Linux; U; Linux i570 ; en-US) Gecko/20100101 Firefox/49.9",
        "Mozilla/5.0 (Windows NT 10.2; WOW64; en-US) AppleWebKit/603.2 (KHTML, like Gecko) Chrome/55.0.1299.311 Safari/535",
        "Mozilla/5.0 (Windows; Windows NT 10.5; x64; en-US) AppleWebKit/603.39 (KHTML, like Gecko) Chrome/52.0.1443.139 Safari/536.6 Edge/13.79436",
        "Mozilla/5.0 (Linux; U; Android 5.1; SM-G9350T Build/MMB29M) AppleWebKit/537.15 (KHTML, like Gecko)  Chrome/55.0.2552.307 Mobile Safari/600.8",
        "Mozilla/5.0 (Android; Android 6.0; SAMSUNG SM-D9350V Build/MDB08L) AppleWebKit/535.30 (KHTML, like Gecko)  Chrome/53.0.1345.278 Mobile Safari/537.4",
        "Mozilla/5.0 (Windows; Windows NT 10.0;) AppleWebKit/534.44 (KHTML, like Gecko) Chrome/47.0.3503.387 Safari/601",
    ];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly array $userAgents = self::USER_AGENTS,
        private readonly int $repeatOnFailure = 1,
    ) {
    }

    public function getRandomUserAgent(): string
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $repeatsLeft = $this->repeatOnFailure;
        do {
            $modifiedOptions = $options;
            if (!isset($modifiedOptions['headers']['User-Agent'])) {
                $modifiedOptions['headers']['User-Agent'] = $this->getRandomUserAgent();
            }
            $response =  $this->client->request($method, $url, $modifiedOptions);

            //When we get a 503, 403 or 429, we assume that the server is blocking us and try again with a different user agent
            if (!in_array($response->getStatusCode(), [403, 429, 503], true)) {
                return $response;
            }

            //Otherwise we try again with a different user agent, until we run out of retries
        } while ($repeatsLeft-- > 0);

        return $response;
    }

    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return new self($this->client->withOptions($options), $this->userAgents, $this->repeatOnFailure);
    }
}
