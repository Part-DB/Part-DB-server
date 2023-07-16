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


namespace App\Controller;

use App\Services\OAuth\OAuthTokenManager;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use function Symfony\Component\Translation\t;

#[Route('/oauth/client')]
class OAuthClientController extends AbstractController
{
    public function __construct(private readonly ClientRegistry $clientRegistry, private readonly OAuthTokenManager $tokenManager)
    {

    }

    #[Route('/{name}/connect', name: 'oauth_client_connect')]
    public function connect(string $name): Response
    {
        return $this->clientRegistry
            ->getClient($name) // key used in config/packages/knpu_oauth2_client.yaml
            ->redirect();
    }

    #[Route('/{name}/check', name: 'oauth_client_check')]
    public function check(string $name, Request $request): Response
    {
        $client = $this->clientRegistry->getClient($name);

        $access_token = $client->getAccessToken();
        $this->tokenManager->saveToken($name, $access_token);

        $this->addFlash('success', t('oauth_client.flash.connection_successful'));

        return $this->redirectToRoute('homepage');
    }
}