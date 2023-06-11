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

namespace App\EventSubscriber\UserSystem;

use App\Entity\UserSystem\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * This event subscriber redirects a user to its settings page, when it needs to change its password or is enforced
 * to setup a 2FA method (enforcement can be set per group).
 * In this cases the user is unable to access sites other than the whitelisted (see ALLOWED_ROUTES).
 */
final class PasswordChangeNeededSubscriber implements EventSubscriberInterface
{
    /**
     * @var string[] The routes the user is allowed to access without being redirected.
     *               This should be only routes related to login/logout and user settings
     */
    public const ALLOWED_ROUTES = [
        '2fa_login',
        '2fa_login_check',
        'user_settings',
        'club_base_register_u2f',
        'logout',
    ];

    /**
     * @var string The route the user will redirected to, if he needs to change this password
     */
    public const REDIRECT_TARGET = 'user_settings';

    public function __construct(private readonly \Symfony\Bundle\SecurityBundle\Security $security, private readonly HttpUtils $httpUtils)
    {
    }

    /**
     * This function is called when the kernel encounters a request.
     * It checks if the user must change its password or add an 2FA mehtod and redirect it to the user settings page,
     * if needed.
     */
    public function redirectToSettingsIfNeeded(RequestEvent $event): void
    {
        $user = $this->security->getUser();
        $request = $event->getRequest();

        if (!$event->isMainRequest()) {
            return;
        }
        if (!$user instanceof User) {
            return;
        }

        //Abort if we dont need to redirect the user.
        if (!$user->isNeedPwChange() && !static::TFARedirectNeeded($user)) {
            return;
        }

        //Check for a whitelisted URL
        foreach (static::ALLOWED_ROUTES as $route) {
            //Dont do anything if we encounter an allowed route
            if ($this->httpUtils->checkRequestPath($request, $route)) {
                return;
            }
        }

        /* Dont redirect tree endpoints, as this would cause trouble and creates multiple flash
        warnigs for one page reload */
        if (str_contains($request->getUri(), '/tree/')) {
            return;
        }

        /** @var Session $session */
        $session = $request->getSession();
        $flashBag = $session->getFlashBag();

        //Show appropriate message to user about the reason he was redirected
        if ($user->isNeedPwChange()) {
            $flashBag->add('warning', 'user.pw_change_needed.flash');
        }

        if (static::TFARedirectNeeded($user)) {
            $flashBag->add('warning', 'user.2fa_needed.flash');
        }

        $event->setResponse($this->httpUtils->createRedirectResponse($request, static::REDIRECT_TARGET));
    }

    /**
     * Check if a redirect because of a missing 2FA method is needed.
     * That is the case if the group of the user enforces 2FA, but the user has neither Google Authenticator nor an
     * U2F key setup.
     *
     * @param User $user the user for which should be checked if it needs to be redirected
     *
     * @return bool true if the user needs to be redirected
     */
    public static function TFARedirectNeeded(User $user): bool
    {
        $tfa_enabled = $user->isWebAuthnAuthenticatorEnabled() || $user->isGoogleAuthenticatorEnabled();

        return $user->getGroup() instanceof \App\Entity\UserSystem\Group && $user->getGroup()->isEnforce2FA() && !$tfa_enabled;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'redirectToSettingsIfNeeded',
        ];
    }
}
