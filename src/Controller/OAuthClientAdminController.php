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

use App\Entity\UserSystem\ApiTokenLevel;
use App\Services\OAuth\OAuthClientAdminManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

/**
 * Admin-only overview of every OAuth2 client (League\Bundle\OAuth2ServerBundle) registered against this
 * instance - via RFC 7591 self-registration if enabled (App\Controller\OAuth\ClientRegistrationController)
 * or manual registration right here. This page is the moderation surface for open self-registration: an
 * admin can see what has registered and permanently remove a client (and everything it was ever granted)
 * if it looks abusive or abandoned - and, regardless of whether self-registration is enabled, can
 * register a client by hand for a known/trusted app.
 *
 * Only reachable if the OAuth2 server itself is enabled (OAUTH_SERVER_ENABLED, disabled by default).
 */
#[Route(path: '/tools/oauth_clients', condition: "env('OAUTH_SERVER_ENABLED') == '1' or env('OAUTH_SERVER_ENABLED') == 'true'")]
class OAuthClientAdminController extends AbstractController
{
    public function __construct(private readonly OAuthClientAdminManager $manager)
    {
    }

    #[Route(path: '', name: 'oauth_clients_list', methods: ['GET'])]
    public function list(): Response
    {
        $this->denyAccessUnlessGranted('@system.manage_oauth_clients');

        return $this->render('tools/oauth_clients/oauth_clients.html.twig', [
            'clients' => $this->manager->listClientsWithLiveTokenCounts(),
            'scope_levels' => ApiTokenLevel::cases(),
        ]);
    }

    #[Route(path: '/create', name: 'oauth_clients_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('@system.manage_oauth_clients');

        if (!$this->isCsrfTokenValid('oauth_client_create', $request->request->get('_token'))) {
            $this->addFlash('error', 'csfr_invalid');
            return $this->redirectToRoute('oauth_clients_list');
        }

        $name = $request->request->getString('name');
        $redirectUris = array_values(array_filter(array_map(
            trim(...),
            explode("\n", $request->request->getString('redirect_uris')),
        ), static fn (string $uri): bool => '' !== $uri));
        $scopes = array_values(array_filter($request->request->all('scopes'), \is_string(...)));

        try {
            $client = $this->manager->createClient($name, $redirectUris, $scopes);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('oauth_clients_list');
        }

        $this->addFlash('success', t('oauth_clients.created', ['%client_id%' => $client->getIdentifier()]));
        return $this->redirectToRoute('oauth_clients_list');
    }

    #[Route(path: '/{identifier}/delete', name: 'oauth_clients_delete', methods: ['DELETE'])]
    public function delete(string $identifier, Request $request): Response
    {
        $this->denyAccessUnlessGranted('@system.manage_oauth_clients');

        if (!$this->isCsrfTokenValid('oauth_client_delete'.$identifier, $request->request->get('_token'))) {
            $this->addFlash('error', 'csfr_invalid');
            return $this->redirectToRoute('oauth_clients_list');
        }

        if (!$this->manager->deleteClient($identifier)) {
            $this->addFlash('error', 'tfa_u2f.u2f_delete.not_existing');
            return $this->redirectToRoute('oauth_clients_list');
        }

        $this->addFlash('success', 'oauth_clients.deleted');
        return $this->redirectToRoute('oauth_clients_list');
    }
}
