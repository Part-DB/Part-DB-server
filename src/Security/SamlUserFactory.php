<?php

declare(strict_types=1);

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
use Nbgrp\OneloginSamlBundle\Security\Http\Authenticator\Token\SamlToken;
use Nbgrp\OneloginSamlBundle\Security\User\SamlUserFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @see \App\Tests\Security\SamlUserFactoryTest
 */
class SamlUserFactory implements SamlUserFactoryInterface, EventSubscriberInterface
{
    private readonly array $saml_role_mapping;

    public function __construct(private readonly EntityManagerInterface $em, ?array $saml_role_mapping, private readonly bool $update_group_on_login)
    {
        $this->saml_role_mapping = $saml_role_mapping ?: [];
    }

    final public const SAML_PASSWORD_PLACEHOLDER = '!!SAML!!';

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
            $this->em->flush();
        }
    }

    /**
     * Maps the given SAML attributes to a local group.
     * @param  array  $attributes The SAML attributes
     */
    public function mapSAMLAttributesToLocalGroup(array $attributes): ?Group
    {
        //Extract the roles from the SAML attributes
        $roles = $attributes['group'] ?? [];
        $group_id = $this->mapSAMLRolesToLocalGroupID($roles);

        //Check if we can find a group with the given ID
        if ($group_id !== null) {
            $group = $this->em->find(Group::class, $group_id);
            if ($group instanceof Group) {
                return $group;
            }
        }

        //If no group was found, return null
        return null;
    }

    /**
     * Maps a list of SAML roles to a local group ID.
     * The first available mapping will be used (so the order of the $map is important, first match wins).
     * @param  array  $roles The list of SAML roles
     * @param  array|null  $map The mapping from SAML roles. If null, the global mapping will be used.
     * @return int|null The ID of the local group or null if no mapping was found.
     */
    public function mapSAMLRolesToLocalGroupID(array $roles, ?array $map = null): ?int
    {
        $map ??= $this->saml_role_mapping;

        //Iterate over the mapping (from first to last) and check if we have a match
        foreach ($map as $saml_role => $group_id) {
            //Skip wildcard
            if ($saml_role === '*') {
                continue;
            }
            if (in_array($saml_role, $roles, true)) {
                return (int) $group_id;
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
