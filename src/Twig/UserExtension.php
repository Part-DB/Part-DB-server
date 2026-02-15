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

use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use App\Entity\UserSystem\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @see \App\Tests\Twig\UserExtensionTest
 */
final readonly class UserExtension
{

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * This function returns the user which has impersonated the current user.
     * If the current user is not impersonated, null is returned.
     * @return User|null
     */
    #[AsTwigFunction(name: 'impersonator_user')]
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

    #[AsTwigFunction(name: 'impersonation_active')]
    public function isImpersonationActive(): bool
    {
        return $this->security->isGranted('IS_IMPERSONATOR');
    }

    #[AsTwigFunction(name: 'impersonation_path')]
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
    #[AsTwigFilter(name: 'remove_locale_from_path')]
    public function removeLocaleFromPath(string $path): string
    {
        //Ensure the path has the correct format
        if (!preg_match('/^\/\w{2}(?:_\w{2})?\//', $path)) {
            throw new \InvalidArgumentException('The given path is not a localized path!');
        }

        $parts = explode('/', $path);
        //Remove the part with locale
        unset($parts[1]);

        return implode('/', $parts);
    }

}
