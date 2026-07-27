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

use App\Services\OAuth\OAuthClientAdminManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin-only overview of every OAuth2 client (League\Bundle\OAuth2ServerBundle) registered against this
 * instance - almost always RFC 7591 self-registered (App\Controller\OAuth\ClientRegistrationController),
 * since that endpoint is open by design. This page is the moderation surface for that openness: an admin
 * can see what has registered and permanently remove a client (and everything it was ever granted) if it
 * looks abusive or abandoned.
 */
#[Route(path: '/tools/oauth_clients')]
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
        ]);
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
