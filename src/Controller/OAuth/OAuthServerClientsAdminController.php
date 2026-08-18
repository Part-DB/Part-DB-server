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

namespace App\Controller\OAuth;

use App\Form\OAuth\OAuthClientData;
use App\Form\OAuth\OAuthClientType;
use App\Services\OAuth\OAuthClientAdminManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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
class OAuthServerClientsAdminController extends AbstractController
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

    #[Route(path: '/new', name: 'oauth_clients_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('@system.manage_oauth_clients');
        //Enforce full login for this action, because it is a high-risk action that can be used to create a client that can impersonate any user.
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $data = new OAuthClientData();
        $form = $this->createForm(OAuthClientType::class, $data, [
            'action' => $this->generateUrl('oauth_clients_new'),
            'csrf_token_id' => 'oauth_client_new',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $client = $this->manager->createClient($data->name, $data->getRedirectUriList(), $data->scopes);
                $this->addFlash('success', t('oauth_clients.created', ['%client_id%' => $client->getIdentifier()]));

                return $this->redirectToRoute('oauth_clients_list');
            } catch (\InvalidArgumentException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('tools/oauth_clients/oauth_client_new.html.twig', [
            'create_form' => $form,
        ]);
    }

    #[Route(path: '/{identifier}/edit', name: 'oauth_clients_edit', methods: ['GET', 'POST'])]
    public function edit(string $identifier, Request $request): Response
    {
        $this->denyAccessUnlessGranted('@system.manage_oauth_clients');
        //Same reasoning as create(): editing a client's redirect URIs/scopes is just as high-risk as creating one.
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $client = $this->manager->findClient($identifier);
        if (null === $client) {
            throw $this->createNotFoundException();
        }

        $data = OAuthClientData::fromClient($client);
        $form = $this->createForm(OAuthClientType::class, $data, [
            'is_edit' => true,
            'action' => $this->generateUrl('oauth_clients_edit', ['identifier' => $identifier]),
            'csrf_token_id' => 'oauth_client_edit'.$identifier,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->manager->updateClient($identifier, $data->name, $data->getRedirectUriList(), $data->scopes, $data->active);
                $this->addFlash('success', 'oauth_clients.updated');

                return $this->redirectToRoute('oauth_clients_list');
            } catch (\InvalidArgumentException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('tools/oauth_clients/oauth_client_edit.html.twig', [
            'client' => $client,
            'edit_form' => $form,
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
