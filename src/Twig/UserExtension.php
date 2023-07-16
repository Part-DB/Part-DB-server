<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Twig;

use App\Entity\Base\AbstractDBElement;
use App\Entity\UserSystem\User;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Repository\LogEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * @see \App\Tests\Twig\UserExtensionTest
 */
final class UserExtension extends AbstractExtension
{
    private readonly LogEntryRepository $repo;

    public function __construct(EntityManagerInterface $em,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator)
    {
        $this->repo = $em->getRepository(AbstractLogEntry::class);
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('remove_locale_from_path', fn(string $path): string => $this->removeLocaleFromPath($path)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            /* Returns the user which has edited the given entity the last time. */
            new TwigFunction('last_editing_user', fn(AbstractDBElement $element): ?User => $this->repo->getLastEditingUser($element)),
            /* Returns the user which has created the given entity. */
            new TwigFunction('creating_user', fn(AbstractDBElement $element): ?User => $this->repo->getCreatingUser($element)),
            new TwigFunction('impersonator_user', $this->getImpersonatorUser(...)),
            new TwigFunction('impersonation_active', $this->isImpersonationActive(...)),
            new TwigFunction('impersonation_path', $this->getImpersonationPath(...)),
        ];
    }

    /**
     * This function returns the user which has impersonated the current user.
     * If the current user is not impersonated, null is returned.
     * @return User|null
     */
    public function getImpersonatorUser(): ?User
    {
        $token = $this->security->getToken();
        if ($token instanceof SwitchUserToken) {
            $tmp = $token->getOriginalToken()->getUser();

            if ($tmp instanceof User) {
                return $tmp;
            }
        }

        return null;
    }

    public function isImpersonationActive(): bool
    {
        return $this->security->isGranted('IS_IMPERSONATOR');
    }

    public function getImpersonationPath(User $user, string $route_name = 'homepage'): string
    {
        if (! $this->security->isGranted('CAN_SWITCH_USER', $user)) {
            throw new AccessDeniedException('You are not allowed to impersonate this user!');
        }

        return $this->urlGenerator->generate($route_name, ['_switch_user' => $user->getUsername()]);
    }

    /**
     * This function/filter generates a path.
     */
    public function removeLocaleFromPath(string $path): string
    {
        //Ensure the path has the correct format
        if (!preg_match('/^\/\w{2}\//', $path)) {
            throw new \InvalidArgumentException('The given path is not a localized path!');
        }

        $parts = explode('/', $path);
        //Remove the part with locale
        unset($parts[1]);

        return implode('/', $parts);
    }

}
