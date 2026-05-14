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

namespace App\Controller;

use App\Services\InfoProviderSystem\SubmittedPageStorage;
use App\Services\InfoProviderSystem\DTOs\BrowserSubmittedPage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Provides the endpoint used by browser extensions to submit the current page's HTML to Part-DB,
 * so that info providers can use it instead of fetching the URL themselves.
 */
#[Route('/tools/info_providers')]
class BrowserPluginController extends AbstractController
{
    private const MAX_HTML_SIZE = 5 * 1024 * 1024; // 5 MB

    public function __construct(private readonly SubmittedPageStorage $browserHtmlStorage)
    {
    }

    /**
     * Accepts a JSON POST body with the HTML of the current page from a browser extension.
     * Stores the HTML in the session via BrowserHtmlSessionStorage and returns a redirect URL
     * pointing to the standard part-creation flow with use_browser_html=1.
     *
     * Expected JSON body: { "html": "<full page HTML>", "url": "https://example.com/product", "provider": "generic_web" }
     * The "provider" field is optional and defaults to "generic_web". Use "ai_web" for the AI extractor.
     * Response: { "redirect_url": "https://partdb.example.com/en/part/from_info_provider/generic_web/https%3A%2F%2F.../create?use_browser_html=1&no_cache=1" }
     */
    #[Route('/browser_html', name: 'browser_plugin_submit_html', methods: ['POST'])]
    public function submitHtml(Request $request,
        #[MapRequestPayload]
        BrowserSubmittedPage $page
    ): JsonResponse
    {
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

        $provider = (string) ($data['provider'] ?? 'generic_web');

        // The maprequestpayload already validates the URL and HTML content:
        $token = $this->browserHtmlStorage->store($page);

        $redirectUrl = $this->generateUrl('info_providers_create_part', [
            'providerKey' => $provider,
            'providerId' => $page->url,
            'submitted_page_token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse(['redirect_url' => $redirectUrl]);
    }
}
