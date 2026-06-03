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
 * It also sets some other headers to make the requests look more like real browser requests.
 * When we get a 503, 403 or 429, we assume that the server is blocking us and try again with a different user agent, until we run out of retries.
 */
final class RandomizeUseragentHttpClient implements HttpClientInterface
{
    private const PROFILES = [
        // --- CHROME ON WINDOWS ---
        'chrome_windows' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36',
            'Sec-Ch-Ua' => '"Google Chrome";v="142", "Chromium";v="142", "Not=A?Brand";v="99"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"Windows"',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        ],

        // --- CHROME ON MACOS ---
        'chrome_mac' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36',
            'Sec-Ch-Ua' => '"Google Chrome";v="141", "Chromium";v="141", "Not=A?Brand";v="99"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"macOS"',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        ],

        // --- EDGE ON WINDOWS ---
        'edge_windows' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0',
            'Sec-Ch-Ua' => '"Microsoft Edge";v="142", "Chromium";v="142", "Not=A?Brand";v="99"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"Windows"',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        ],

        // --- FIREFOX ON WINDOWS ---
        'firefox_windows' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:138.0) Gecko/20100101 Firefox/138.0',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/png,image/svg+xml,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            // Firefox does not send Sec-Ch-Ua headers by default
        ],

        // --- FIREFOX ON LINUX ---
        'firefox_linux' => [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:137.0) Gecko/20100101 Firefox/137.0',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/png,image/svg+xml,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
        ],

        // --- SAFARI ON MACOS ---
        'safari_mac' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ],

        // --- CHROME ON ANDROID (Mobile) ---
        'chrome_android' => [
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36',
            'Sec-Ch-Ua' => '"Google Chrome";v="142", "Chromium";v="142", "Not=A?Brand";v="99"',
            'Sec-Ch-Ua-Mobile' => '?1',
            'Sec-Ch-Ua-Platform' => '"Android"',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        ],

        // --- SAFARI ON IPHONE (Mobile) ---
        'safari_iphone' => [
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.9',
        ],
    ];

    private const COMMON_HEADERS = [
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Accept-Language' => 'en-US,en;q=0.9',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-Site' => 'none',
        'Sec-Fetch-User' => '?1',
        'Upgrade-Insecure-Requests' => '1',
    ];

    private const ENTRY_REFERERS = [
        'https://www.google.com/',
        'https://www.bing.com/',
        'https://duckduckgo.com/',
        'https://t.co/', // Twitter/X shortener
        'https://www.reddit.com/',
    ];

    private ?string $lastUrl = null;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly int $repeatOnFailure = 1,
    ) {
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $repeatsLeft = $this->repeatOnFailure;
        do {
            $profile = self::PROFILES[array_rand(self::PROFILES)];

            // Merge common headers with the specific browser profile
            $headers = array_merge(self::COMMON_HEADERS, $profile);

            //Add a Referer header if not already set, to make it look more like a real browser request. We use the last URL we visited as the referer, to simulate internal navigation. If we don't have a last URL (first request), we pick a random entry point from common referers.
            if (!isset($options['headers']['Referer'])) {
                if ($this->lastUrl !== null) {
                    // If we have a previous URL, use it (Internal Navigation)
                    $headers['Referer'] = $this->lastUrl;
                } else {
                    // First request? Pick an entry point (External Entry)
                    $headers['Referer'] = self::ENTRY_REFERERS[array_rand(self::ENTRY_REFERERS)];
                }
            }

            // Allow manual overrides from $options
            $options['headers'] = array_merge($headers, $options['headers'] ?? []);

            $response = $this->client->request($method, $url, $options);

            //When we get a 503, 403 or 429, we assume that the server is blocking us and try again with a different user agent
            if (!in_array($response->getStatusCode(), [403, 429, 503], true)) {
                $this->lastUrl = $url; // Update last visited URL for referer in the next request
                return $response;
            }

            //Otherwise we try again with a different user agent, until we run out of retries
            usleep(5000); // Sleep for 5ms to avoid hammering the server too hard in case of multiple retries
        } while ($repeatsLeft-- > 0);

        return $response;
    }

    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return new self($this->client->withOptions($options), $this->repeatOnFailure);
    }
}
