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


namespace App\Security;

use App\Entity\UserSystem\ApiToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Authenticator similar to the builtin AccessTokenAuthenticator, but we return a Token here which contains information
 * about the used token.
 */
class ApiTokenAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        #[Autowire(service: 'security.access_token_extractor.main')]
        private readonly AccessTokenExtractorInterface $accessTokenExtractor,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $realm = 'api',
    ) {
    }

    /**
     * Gets the ApiToken belonging to the given accessToken string.
     * If the token is invalid or expired, an exception is thrown and authentication fails.
     * @param  string  $accessToken
     * @return ApiToken
     */
    private function getTokenFromString(#[\SensitiveParameter] string $accessToken): ApiToken
    {
        $repo = $this->entityManager->getRepository(ApiToken::class);
        $token = $repo->findOneBy(['token' => $accessToken]);

        if (!$token instanceof ApiToken) {
            throw new BadCredentialsException();
        }

        if (!$token->isValid()) {
            throw new CustomUserMessageAuthenticationException('Token expired');
        }

        $old_time = $token->getLastTimeUsed();
        //Set the last used date of the token
        $token->setLastTimeUsed(new \DateTimeImmutable());
        //Only flush the token if the last used date change is more than 10 minutes
        //For performance reasons we don't want to flush the token every time it is used, but only if it is used more than 10 minutes after the last time it was used
        //If a flush is later in the code we don't want to flush the token again
        if ($old_time === null || $old_time->diff($token->getLastTimeUsed())->i > 10) {
            $this->entityManager->flush();
        }

        return $token;
    }

    public function supports(Request $request): ?bool
    {
        return null === $this->accessTokenExtractor->extractAccessToken($request) ? false : null;
    }

    public function authenticate(Request $request): Passport
    {
        $accessToken = $this->accessTokenExtractor->extractAccessToken($request);
        if (!$accessToken) {
            throw new BadCredentialsException('Invalid credentials.');
        }

        $apiToken = $this->getTokenFromString($accessToken);
        $userBadge = new UserBadge($apiToken->getUser()?->getUserIdentifier() ?? throw new BadCredentialsException('Invalid credentials.'));
        $apiBadge = new ApiTokenBadge($apiToken);

        return new SelfValidatingPassport($userBadge, [$apiBadge]);
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return new ApiTokenAuthenticatedToken(
            $passport->getUser(),
            $firewallName,
            $passport->getUser()->getRoles(),
            $passport->getBadge(ApiTokenBadge::class)?->getApiToken() ?? throw new \LogicException('Passport does not contain an API token.')
        );
    }


    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $errorMessage = $this->translator->trans($exception->getMessageKey(), $exception->getMessageData(),
            'security');

        return new Response(
            null,
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => $this->getAuthenticateHeader($errorMessage)]
        );
    }

    /**
     * @see https://datatracker.ietf.org/doc/html/rfc6750#section-3
     */
    private function getAuthenticateHeader(?string $errorDescription = null): string
    {
        $data = [
            'realm' => $this->realm,
            'error' => 'invalid_token',
            'error_description' => $errorDescription,
        ];
        $values = [];
        foreach ($data as $k => $v) {
            if (null === $v || '' === $v) {
                continue;
            }
            $values[] = sprintf('%s="%s"', $k, $v);
        }

        return sprintf('Bearer %s', implode(',', $values));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }
}