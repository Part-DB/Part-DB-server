<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\DataFixtures;

use App\Entity\UserSystem\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserFixtures extends Fixture
{
    protected $encoder;
    protected $em;

    public function __construct(UserPasswordEncoderInterface $encoder, EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager): void
    {
        //Reset autoincrement
        $this->em->getConnection()->exec('ALTER TABLE `users` AUTO_INCREMENT = 1;');

        $anonymous = new User();
        $anonymous->setName('anonymous');
        $anonymous->setGroup($this->getReference(GroupFixtures::READONLY));
        $anonymous->setNeedPwChange(false);
        $anonymous->setPassword($this->encoder->encodePassword($anonymous, 'test'));
        $manager->persist($anonymous);

        $admin = new User();
        $admin->setName('admin');
        $admin->setPassword($this->encoder->encodePassword($admin, 'test'));
        $admin->setNeedPwChange(false);
        $admin->setGroup($this->getReference(GroupFixtures::ADMINS));
        $manager->persist($admin);

        $user = new User();
        $user->setName('user');
        $user->setNeedPwChange(false);
        $user->setFirstName('Test')->setLastName('User');
        $user->setPassword($this->encoder->encodePassword($user, 'test'));
        $user->setGroup($this->getReference(GroupFixtures::USERS));
        $manager->persist($user);

        $noread = new User();
        $noread->setName('noread');
        $noread->setNeedPwChange(false);
        $noread->setPassword($this->encoder->encodePassword($noread, 'test'));
        $manager->persist($noread);

        $manager->flush();
    }
}
