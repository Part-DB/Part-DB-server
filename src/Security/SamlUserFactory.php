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

use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Hslavich\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken;
use Hslavich\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;

class SamlUserFactory implements SamlUserFactoryInterface, EventSubscriberInterface
{
    private EntityManagerInterface $em;
    private array $saml_role_mapping;
    private bool $update_group_on_login;

    public function __construct(EntityManagerInterface $entityManager, ?array $saml_role_mapping, bool $update_group_on_login)
    {
        $this->em = $entityManager;
        if ($saml_role_mapping) {
            $this->saml_role_mapping = $saml_role_mapping;
        } else {
            $this->saml_role_mapping = [];
        }
        $this->update_group_on_login = $update_group_on_login;
    }

    public const SAML_PASSWORD_PLACEHOLDER = '!!SAML!!';

    public function createUser($username, array $attributes = []): UserInterface
    {
        $user = new User();
        $user->setName($username);
        $user->setNeedPwChange(false);
        $user->setPassword(self::SAML_PASSWORD_PLACEHOLDER);
        //This is a SAML user now!
        $user->setSamlUser(true);

        //Update basic user information
        $user->setSamlAttributes($attributes);

        //Check if we can find a group for this user based on the SAML attributes
        $group = $this->mapSAMLAttributesToLocalGroup($attributes);
        $user->setGroup($group);

        return $user;
    }

    /**
     * This method is called after a successful authentication. It is used to update the group of the user,
     * based on the new SAML attributes.
     * @param  AuthenticationSuccessEvent  $event
     * @return void
     */
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        if (! $this->update_group_on_login) {
            return;
        }

        $token = $event->getAuthenticationToken();
        $user = $token->getUser();
        //Only update the group if the user is a SAML user
        if (! $token instanceof SamlToken || ! $user instanceof User) {
            return;
        }

        //Check if we can find a group for this user based on the SAML attributes
        $group = $this->mapSAMLAttributesToLocalGroup($token->getAttributes());
        //If needed update the group of the user and save it to DB
        if ($group !== $user->getGroup()) {
            $user->setGroup($group);
            $this->em->flush($user);
        }
    }

    /**
     * Maps the given SAML attributes to a local group.
     * @param  array  $attributes The SAML attributes
     * @return Group|null
     */
    public function mapSAMLAttributesToLocalGroup(array $attributes): ?Group
    {
        //Extract the roles from the SAML attributes
        $roles = $attributes['group'] ?? [];
        $group_id = $this->mapSAMLRolesToLocalGroupID($roles);

        //Check if we can find a group with the given ID
        if ($group_id !== null) {
            $group = $this->em->find(Group::class, $group_id);
            if ($group !== null) {
                return $group;
            }
        }

        //If no group was found, return null
        return null;
    }

    /**
     * Maps a list of SAML roles to a local group ID.
     * @param  array  $roles The list of SAML roles
     * @param  array  $map|null The mapping from SAML roles. If null, the global mapping will be used.
     * @return int|null The ID of the local group or null if no mapping was found.
     */
    public function mapSAMLRolesToLocalGroupID(array $roles, array $map = null): ?int
    {
        $map = $map ?? $this->saml_role_mapping;

        //Iterate over all roles and check if we have a mapping for it.
        foreach ($roles as $role) {
            if (array_key_exists($role, $map)) {
                //We use the first available mapping
                return (int) $map[$role];
            }
        }

        //If no applicable mapping was found, check if we have a default mapping
        if (array_key_exists('*', $map)) {
            return (int) $map['*'];
        }

        //If no mapping was found, return null
        return null;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AuthenticationSuccessEvent::class => 'onAuthenticationSuccess',
        ];
    }
}