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


namespace App\Services\OAuth;

use App\Entity\OAuthToken;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Token\AccessTokenInterface;

final class OAuthTokenManager
{
    public function __construct(private readonly ClientRegistry $clientRegistry, private readonly EntityManagerInterface $entityManager)
    {

    }

    /**
     * Saves the given token to the database, so it can be retrieved later
     * @param  string  $app_name
     * @param  AccessTokenInterface  $token
     * @return void
     */
    public function saveToken(string $app_name, AccessTokenInterface $token): void
    {
        //Check if we already have a token for this app
        $tokenEntity = $this->entityManager->getRepository(OAuthToken::class)->findOneBy(['name' => $app_name]);

        //If the token was already existing, we just replace it with the new one
        if ($tokenEntity) {
            $tokenEntity->replaceWithNewToken($token);

            $this->entityManager->flush($tokenEntity);

            //We are done
            return;
        }

        //If the token was not existing, we create a new one
        $tokenEntity = OAuthToken::fromAccessToken($token, $app_name);
        $this->entityManager->persist($tokenEntity);
        $this->entityManager->flush($tokenEntity);

        return;
    }

    /**
     * Returns the token for the given app name
     * @param  string  $app_name
     * @return OAuthToken|null
     */
    public function getToken(string $app_name): ?OAuthToken
    {
        return $this->entityManager->getRepository(OAuthToken::class)->findOneBy(['name' => $app_name]);
    }

    /**
     * Checks if a token for the given app name is existing
     * @param  string  $app_name
     * @return bool
     */
    public function hasToken(string $app_name): bool
    {
        return $this->getToken($app_name) !== null;
    }

    /**
     * This function refreshes the token for the given app name. The new token is saved to the database
     * The app_name must be registered in the knpu_oauth2_client.yaml
     * @param  string  $app_name
     * @return OAuthToken
     * @throws \Exception
     */
    public function refreshToken(string $app_name): OAuthToken
    {
        $token = $this->getToken($app_name);

        if (!$token) {
            throw new \Exception('No token was saved yet for '.$app_name);
        }

        $client = $this->clientRegistry->getClient($app_name);
        $new_token = $client->refreshAccessToken($token->getRefreshToken());

        //Persist the token
        $token->replaceWithNewToken($new_token);
        $this->entityManager->flush($token);

        return $token;
    }

    /**
     * This function returns the token of the given app name
     * @param  string  $app_name
     * @return OAuthToken|null
     */
    public function getAlwaysValidTokenString(string $app_name): ?string
    {
        //Get the token for the application
        $token = $this->getToken($app_name);

        //If the token is not existing, we return null
        if (!$token) {
            return null;
        }

        //If the token is still valid, we return it
        if (!$token->hasExpired()) {
            return $token->getToken();
        }

        //If the token is expired, we refresh it
        $this->refreshToken($app_name);

        //And return the new token
        return $token->getToken();
    }
}