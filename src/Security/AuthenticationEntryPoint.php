<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

use function Symfony\Component\Translation\t;

/**
 * This class decides, what to do, when a user tries to access a page, that requires authentication and he is not
 * authenticated / logged in yet.
 * For browser requests, the user is redirected to the login page, for API requests, a 401 response with a JSON encoded
 * message is returned.
 */
class AuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        //Check if the request is an API request
        if ($this->isJSONRequest($request)) {
            //If it is, we return a 401 response with a JSON body
            return new JsonResponse([
                'title' => 'Unauthorized',
                'detail' => 'Authentication is required. Please pass a valid API token in the Authorization header.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        //Otherwise we redirect to the login page

        //Add a nice flash message to make it clear what happened
        $request->getSession()->getFlashBag()->add('error', t('login.flash.access_denied_please_login'));

        return new RedirectResponse($this->urlGenerator->generate('login'));
    }

    private function isJSONRequest(Request $request): bool
    {
        //If either the content type or accept header is a json type, we assume it is an API request
        $contentType = $request->headers->get('Content-Type');
        $accept = $request->headers->get('Accept');

        return str_contains($contentType, 'json') || str_contains($accept, 'json');
    }
}