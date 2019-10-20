<?php

namespace App\DataFixtures;

use App\Entity\UserSystem\Group;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class GroupFixtures extends Fixture
{
    public const ADMINS = 'group-admin';
    public const USERS = 'group-users';
    public const READONLY = 'group-readonly';

    public function load(ObjectManager $manager)
    {
        $admins = new Group();
        $admins->setName('admins');

        $this->setReference(self::ADMINS, $admins);
        $manager->persist($admins);

        $readonly = new Group();
        $readonly->setName('readonly');

        $this->setReference(self::READONLY, $readonly);
        $manager->persist($readonly);

        $users = new Group();
        $users->setName('users');

        $this->setReference(self::USERS, $users);
        $manager->persist($users);

        $manager->flush();
    }
}
