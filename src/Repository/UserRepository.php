<?php
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

namespace App\Repository;

use App\Entity\UserSystem\User;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends NamedDBElementRepository implements PasswordUpgraderInterface
{
    protected $anonymous_user;

    /**
     * Returns the anonymous user.
     * The result is cached, so the database is only called once, after the anonymous user was found.
     *
     * @return User|null
     */
    public function getAnonymousUser() : ?User
    {
        if ($this->anonymous_user === null) {
            $this->anonymous_user = $this->findOneBy([
                'id' => User::ID_ANONYMOUS,
            ]);
        }

        return $this->anonymous_user;
    }

    /**
     * Find a user by its name or its email. Useful for login or password reset purposes.
     * @param string $name_or_password The username or the email of the user that should be found
     * @return User|null The user if it is existing, null if no one matched the criteria
     */
    public function findByEmailOrName(string $name_or_password) : ?User
    {
        if (empty($name_or_password)) {
            return null;
        }

        $qb = $this->createQueryBuilder('u');
        $qb->select('u')
            ->where('u.name = (:name)')
            ->orWhere('u.email = (:email)');

        $qb->setParameters(['email' => $name_or_password, 'name' => $name_or_password]);

        try {
            return $qb->getQuery()->getOneOrNullResult();
        } catch (NonUniqueResultException $exception) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function upgradePassword(UserInterface $user, string $newEncodedPassword): void
    {
        if ($user instanceof User) {
            $user->setPassword($newEncodedPassword);
            $this->getEntityManager()->flush($user);
        }
    }
}
