<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
    public const ADMIN = 'user-admin';

    public function __construct(protected UserPasswordHasherInterface $encoder, protected EntityManagerInterface $em)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $anonymous = new User();
        $anonymous->setName('anonymous');
        $anonymous->setGroup($this->getReference(GroupFixtures::READONLY, Group::class));
        $anonymous->setNeedPwChange(false);
        $anonymous->setPassword($this->encoder->hashPassword($anonymous, 'test'));
        $manager->persist($anonymous);

        $admin = new User();
        $admin->setName('admin');
        $admin->setPassword($this->encoder->hashPassword($admin, 'test'));
        $admin->setNeedPwChange(false);
        $admin->setGroup($this->getReference(GroupFixtures::ADMINS, Group::class));
        $manager->persist($admin);
        $this->addReference(self::ADMIN, $admin);

        $user = new User();
        $user->setName('user');
        $user->setNeedPwChange(false);
        $user->setEmail('user@invalid.invalid');
        $user->setFirstName('Test')->setLastName('User');
        $user->setPassword($this->encoder->hashPassword($user, 'test'));
        $user->setGroup($this->getReference(GroupFixtures::USERS, Group::class));
        $manager->persist($user);

        $noread = new User();
        $noread->setName('noread');
        $noread->setNeedPwChange(false);
        $noread->setPassword($this->encoder->hashPassword($noread, 'test'));
        $manager->persist($noread);

        $manager->flush();

        //Ensure that the anonymous user has the ID 0
        $manager->getRepository(User::class)->changeID($anonymous, User::ID_ANONYMOUS);
    }

    public function getDependencies(): array
    {
        return [
            GroupFixtures::class,
        ];
    }
}
