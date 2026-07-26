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

namespace App\Security\OAuth\Repository;

use App\Entity\UserSystem\OAuth\OAuthAuthCode;
use App\Entity\UserSystem\OAuth\OAuthClient;
use App\Entity\UserSystem\User;
use App\Security\OAuth\Entity\TransientAuthCode;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;

/**
 * See TransientAuthCode and OAuthAuthCode for why this only ever persists a small revocation record,
 * never the code's actual content.
 */
class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new TransientAuthCode();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $client = $authCodeEntity->getClient();
        if (!$client instanceof OAuthClient) {
            throw new \LogicException('Expected an OAuthClient instance.');
        }

        $user = $this->entityManager->find(User::class, $authCodeEntity->getUserIdentifier());
        if (!$user instanceof User) {
            throw new \LogicException('Could not resolve the user the authorization code was issued to.');
        }

        $scopeIdentifiers = array_map(
            static fn ($scope) => $scope->getIdentifier(),
            $authCodeEntity->getScopes()
        );

        $authCode = new OAuthAuthCode(
            $authCodeEntity->getIdentifier(),
            $client,
            $user,
            $scopeIdentifiers,
            $authCodeEntity->getExpiryDateTime(),
            $authCodeEntity->getRedirectUri(),
        );

        $this->entityManager->persist($authCode);
        $this->entityManager->flush();
    }

    public function revokeAuthCode(string $codeId): void
    {
        $authCode = $this->findByIdentifier($codeId);
        if ($authCode instanceof OAuthAuthCode) {
            $authCode->setRevoked(true);
            $this->entityManager->flush();
        }
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        $authCode = $this->findByIdentifier($codeId);

        //A code we have no record of can't be validated, so treat it as revoked/invalid.
        return !$authCode instanceof OAuthAuthCode || $authCode->isRevoked();
    }

    private function findByIdentifier(string $codeId): ?OAuthAuthCode
    {
        return $this->entityManager->getRepository(OAuthAuthCode::class)
            ->findOneBy(['identifier' => $codeId]);
    }
}
