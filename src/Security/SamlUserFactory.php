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

namespace App\Security;

use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Hslavich\OneloginSamlBundle\Event\AbstractUserEvent;
use Hslavich\OneloginSamlBundle\Event\UserCreatedEvent;
use Hslavich\OneloginSamlBundle\Event\UserModifiedEvent;
use Hslavich\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken;
use Hslavich\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class SamlUserFactory implements SamlUserFactoryInterface, EventSubscriberInterface
{
    public const SAML_PASSWORD_PLACEHOLDER = '!!SAML!!';

    private Security $security;
    private EntityManagerInterface $entityManager;

    public function __construct(Security $security, EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
    }

    public function createUser($username, array $attributes = []): UserInterface
    {
        $user = new User();
        $user->setName($username);
        $user->setNeedPwChange(false);
        $user->setPassword(self::SAML_PASSWORD_PLACEHOLDER);
        //This is a SAML user now!
        $user->setSamlUser(true);

        $this->updateUserInfoFromSAMLAttributes($user, $attributes);

        return $user;
    }

    public function updateAndPersistUser(AbstractUserEvent $event): void
    {
        $user = $event->getUser();
        $token = $this->security->getToken();

        if (!$user instanceof User) {
            throw new \RuntimeException('User must be an instance of '.User::class);
        }
        if (!$token instanceof SamlToken) {
            throw new \RuntimeException('Token must be an instance of '.SamlToken::class);
        }

        $attributes = $token->getAttributes();

        //Update the user info based on the SAML attributes
        $this->updateUserInfoFromSAMLAttributes($user, $attributes);

        //Persist the user
        $this->entityManager->persist($user);

        //Flush the entity manager
        $this->entityManager->flush();
    }

    public static function getSubscribedEvents()
    {
        return [
            UserCreatedEvent::class => 'updateAndPersistUser',
            UserModifiedEvent::class => 'updateAndPersistUser',
        ];
    }

    /**
     * Sets the SAML attributes to the user.
     * @param  User  $user
     * @param  array  $attributes
     * @return void
     */
    public function updateUserInfoFromSAMLAttributes(User $user, array $attributes): void
    {
        //When mail attribute exists, set it
        if (isset($attributes['email'])) {
            $user->setEmail($attributes['email'][0]);
        }
        //When first name attribute exists, set it
        if (isset($attributes['firstName'])) {
            $user->setFirstName($attributes['firstName'][0]);
        }
        //When last name attribute exists, set it
        if (isset($attributes['lastName'])) {
            $user->setLastName($attributes['lastName'][0]);
        }
        if (isset($attributes['department'])) {
            $user->setDepartment($attributes['department'][0]);
        }

        //Use X500 attributes as userinfo
        if (isset($attributes['urn:oid:2.5.4.42'])) {
            $user->setFirstName($attributes['urn:oid:2.5.4.42'][0]);
        }
        if (isset($attributes['urn:oid:2.5.4.4'])) {
            $user->setLastName($attributes['urn:oid:2.5.4.4'][0]);
        }
        if (isset($attributes['urn:oid:1.2.840.113549.1.9.1'])) {
            $user->setEmail($attributes['urn:oid:1.2.840.113549.1.9.1'][0]);
        }
    }
}