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

namespace App\Security\OAuth;

use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use LogicException;
use Psr\Http\Message\ResponseInterface;

/**
 * league/oauth2-server's default BearerTokenResponse returns a signed JWT as the "access_token" value
 * (via AccessTokenEntityInterface::__toString()/convertToJWT()). We don't want that: our access tokens
 * ARE App\Entity\UserSystem\ApiToken rows (see App\Security\OAuth\Repository\AccessTokenRepository),
 * validated by the existing App\Security\ApiTokenAuthenticator via a plain DB lookup, so the string
 * handed to the client must be exactly ApiToken::getToken() rather than a JWT wrapping it.
 *
 * This is otherwise an exact copy of BearerTokenResponse::generateHttpResponse(), the only change
 * being `$this->accessToken->getIdentifier()` instead of `(string) $this->accessToken`. Overriding this
 * method (rather than just the identifier) is necessary because the base class doesn't expose a smaller
 * extension point for it - see the "extra params" hook, which is for adding *additional* fields, not
 * for changing how access_token itself is rendered.
 */
class OpaqueBearerTokenResponse extends BearerTokenResponse
{
    public function generateHttpResponse(ResponseInterface $response): ResponseInterface
    {
        $expireDateTime = $this->accessToken->getExpiryDateTime()->getTimestamp();

        $responseParams = [
            'token_type' => 'Bearer',
            'expires_in' => $expireDateTime - time(),
            'access_token' => $this->accessToken->getIdentifier(),
        ];

        //$refreshToken is a typed, non-nullable property with no default - isset() (rather than an
        //instanceof/truthiness check, which would throw on an uninitialized typed property) is the
        //correct way to test whether setRefreshToken() was ever called for this response.
        if (isset($this->refreshToken)) {
            $refreshTokenPayload = json_encode([
                'client_id' => $this->accessToken->getClient()->getIdentifier(),
                'refresh_token_id' => $this->refreshToken->getIdentifier(),
                'access_token_id' => $this->accessToken->getIdentifier(),
                'scopes' => $this->accessToken->getScopes(),
                'user_id' => $this->accessToken->getUserIdentifier(),
                'expire_time' => $this->refreshToken->getExpiryDateTime()->getTimestamp(),
            ]);

            if ($refreshTokenPayload === false) {
                throw new LogicException('Error encountered JSON encoding the refresh token payload');
            }

            $responseParams['refresh_token'] = $this->encrypt($refreshTokenPayload);
        }

        $responseParams = json_encode(array_merge($this->getExtraParams($this->accessToken), $responseParams));

        if ($responseParams === false) {
            throw new LogicException('Error encountered JSON encoding response parameters');
        }

        $response = $response
            ->withStatus(200)
            ->withHeader('pragma', 'no-cache')
            ->withHeader('cache-control', 'no-store')
            ->withHeader('content-type', 'application/json; charset=UTF-8');

        $response->getBody()->write($responseParams);

        return $response;
    }
}
