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

namespace App\Form\OAuth;

use League\Bundle\OAuth2ServerBundle\Model\ClientInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Backs App\Form\OAuth\OAuthClientType - the form data of both the "create client" and "edit client" forms
 * rendered by App\Controller\OAuth\OAuthClientAdminController. This is a plain form model, not the League
 * OAuth2ServerBundle ClientInterface itself: mapping the form directly onto a Client would require a
 * secret/identifier at construction time and does not have public setters that line up with form field
 * names, so App\Services\OAuth\OAuthClientAdminManager still does the actual translation into (or update
 * of) a Client after this DTO has passed validation.
 */
final class OAuthClientData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $name = '';

    /**
     * One redirect URI per line - kept as a single textarea-backed string (rather than a CollectionType)
     * to match how admins are used to pasting a handful of URIs at once.
     */
    #[Assert\NotBlank]
    public string $redirect_uris = '';

    /** @var list<string> */
    #[Assert\Count(min: 1)]
    public array $scopes = [];

    public bool $active = true;

    public static function fromClient(ClientInterface $client): self
    {
        $data = new self();
        $data->name = $client->getName();
        $data->redirect_uris = implode("\n", array_map(
            static fn (\Stringable|string $uri): string => (string) $uri,
            $client->getRedirectUris(),
        ));
        $data->scopes = array_map(
            static fn (\Stringable|string $scope): string => (string) $scope,
            $client->getScopes(),
        );
        $data->active = $client->isActive();

        return $data;
    }

    /**
     * @return list<string>
     */
    public function getRedirectUriList(): array
    {
        return array_values(array_filter(array_map(
            trim(...),
            explode("\n", $this->redirect_uris),
        ), static fn (string $uri): bool => '' !== $uri));
    }
}
