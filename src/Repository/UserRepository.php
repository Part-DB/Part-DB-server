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

namespace App\Repository;

use App\Entity\UserSystem\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Parameter;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @extends NamedDBElementRepository<User>
 */
final class UserRepository extends NamedDBElementRepository implements PasswordUpgraderInterface
{
    protected ?User $anonymous_user = null;

    /**
     * Returns the anonymous user.
     * The result is cached, so the database is only called once, after the anonymous user was found.
     * @return User|null The user if it is existing, null if no one matched the criteria
     */
    public function getAnonymousUser(): ?User
    {
        if (!$this->anonymous_user instanceof User) {
            $this->anonymous_user = $this->findOneBy([
                'id' => User::ID_ANONYMOUS,
            ]);
        }

        return $this->anonymous_user;
    }

    /**
     * Find a user by its username.
     * @param  string  $username
     * @return User|null
     */
    public function findByUsername(string $username): ?User
    {
        if ($username === '') {
            return null;
        }

        $qb = $this->createQueryBuilder('u');
        $qb->select('u')
            ->where('u.name = (:name)');

        $qb->setParameter('name', $username);

        try {
            return $qb->getQuery()->getOneOrNullResult();
        } catch (NonUniqueResultException) {
            return null;
        }
    }

    /**
     * Find a user by its name or its email. Useful for login or password reset purposes.
     *
     * @param string $name_or_password The username or the email of the user that should be found
     *
     * @return User|null The user if it is existing, null if no one matched the criteria
     */
    public function findByEmailOrName(string $name_or_password): ?User
    {
        if ($name_or_password === '') {
            return null;
        }

        $qb = $this->createQueryBuilder('u');
        $qb->select('u')
            ->where('u.name = (:name)')
            ->orWhere('u.email = (:email)');

        $qb->setParameter('email', $name_or_password);
        $qb->setParameter('name', $name_or_password);

        try {
            return $qb->getQuery()->getOneOrNullResult();
        } catch (NonUniqueResultException) {
            return null;
        }
    }

    public function upgradePassword(UserInterface|PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if ($user instanceof User) {
            $user->setPassword($newHashedPassword);
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Returns the list of all local users (not SAML users).
     * @return User[]
     */
    public function onlyLocalUsers(): array
    {
        return $this->findBy([
            'saml_user' => false,
        ]);
    }

    /**
     * Returns the list of all SAML users.
     * @return User[]
     */
    public function onlySAMLUsers(): array
    {
        return $this->findBy([
            'saml_user' => true,
        ]);
    }
}
