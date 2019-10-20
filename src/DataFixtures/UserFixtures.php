<?php

namespace App\DataFixtures;

use App\Entity\UserSystem\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
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

    public function load(ObjectManager $manager)
    {
        //Reset autoincrement
        $this->em->getConnection()->exec('ALTER TABLE `users` AUTO_INCREMENT = 1;');

        $anonymous = new User();
        $anonymous->setName('anonymous');
        $anonymous->setGroup($this->getReference(GroupFixtures::READONLY));

        $manager->persist($anonymous);

        $admin = new User();
        $admin->setName('admin');
        $admin->setPassword($this->encoder->encodePassword($admin, 'test'));
        $admin->setGroup($this->getReference(GroupFixtures::ADMINS));
        $manager->persist($admin);

        $user = new User();
        $user->setName('user');
        $user->setNeedPwChange(false);
        $user->setFirstName('Test')->setLastName('User');
        $user->setPassword($this->encoder->encodePassword($user, 'test'));
        $user->setGroup($this->getReference(GroupFixtures::USERS));
        $manager->persist($user);

        $manager->flush();
    }
}
