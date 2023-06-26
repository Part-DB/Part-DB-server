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

use App\Entity\LogSystem\UserLoginLogEntry;
use App\Entity\UserSystem\User;
use App\Services\LogSystem\EventLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This event listener shows a login successful flash to the user after login and write the login to event log.
 */
final class LoginSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly TranslatorInterface $translator, private readonly RequestStack $requestStack, private readonly EventLogger $eventLogger, private readonly bool $gdpr_compliance)
    {
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $ip = $event->getRequest()->getClientIp();
        $log = new UserLoginLogEntry($ip, $this->gdpr_compliance);
        $user = $event->getAuthenticationToken()->getUser();
        if ($user instanceof User && $user->getID()) {
            $log->setTargetElement($user);
            $this->eventLogger->logAndFlush($log);
        }

        /** @var Session $session */
        $session = $this->requestStack->getSession();
        $flashBag = $session->getFlashBag();

        $flashBag->add('notice', $this->translator->trans('flash.login_successful'));
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * ['eventName' => 'methodName']
     *  * ['eventName' => ['methodName', $priority]]
     *  * ['eventName' => [['methodName1', $priority], ['methodName2']]]
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [SecurityEvents::INTERACTIVE_LOGIN => 'onLogin'];
    }
}
