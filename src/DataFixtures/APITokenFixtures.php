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


namespace App\DataFixtures;

use App\Entity\UserSystem\ApiToken;
use App\Entity\UserSystem\ApiTokenLevel;
use App\Entity\UserSystem\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class APITokenFixtures extends Fixture implements DependentFixtureInterface
{
    public const TOKEN_READONLY = 'tcp_readonly';
    public const TOKEN_EDIT = 'tcp_edit';
    public const TOKEN_ADMIN = 'tcp_admin';
    public const TOKEN_FULL = 'tcp_full';
    public const TOKEN_EXPIRED = 'tcp_expired';

    public function load(ObjectManager $manager): void
    {
        /** @var User $admin_user */
        $admin_user = $this->getReference(UserFixtures::ADMIN);

        $read_only_token = new ApiToken();
        $read_only_token->setUser($admin_user);
        $read_only_token->setLevel(ApiTokenLevel::READ_ONLY);
        $read_only_token->setName('read-only');
        $this->setTokenSecret($read_only_token, self::TOKEN_READONLY);
        $manager->persist($read_only_token);

        $editor_token = new ApiToken();
        $editor_token->setUser($admin_user);
        $editor_token->setLevel(ApiTokenLevel::EDIT);
        $editor_token->setName('edit');
        $this->setTokenSecret($editor_token, self::TOKEN_EDIT);
        $manager->persist($editor_token);

        $admin_token = new ApiToken();
        $admin_token->setUser($admin_user);
        $admin_token->setLevel(ApiTokenLevel::ADMIN);
        $admin_token->setName('admin');
        $this->setTokenSecret($admin_token, self::TOKEN_ADMIN);
        $manager->persist($admin_token);

        $full_token = new ApiToken();
        $full_token->setUser($admin_user);
        $full_token->setLevel(ApiTokenLevel::FULL);
        $full_token->setName('full');
        $this->setTokenSecret($full_token, self::TOKEN_FULL);
        $manager->persist($full_token);

        $expired_token = new ApiToken();
        $expired_token->setUser($admin_user);
        $expired_token->setLevel(ApiTokenLevel::FULL);
        $expired_token->setName('expired');
        $expired_token->setValidUntil(new \DateTime('-1 day'));
        $this->setTokenSecret($expired_token, self::TOKEN_EXPIRED);
        $manager->persist($expired_token);

        $manager->flush();
    }

    private function setTokenSecret(ApiToken $token, string $secret): void
    {
        //Access private property
        $reflection = new \ReflectionClass($token);
        $property = $reflection->getProperty('token');
        $property->setValue($token, $secret);
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }
}