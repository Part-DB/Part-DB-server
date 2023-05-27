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
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;

/**
 * The purpose of this event listener is to set the timezone to the one preferred by the user.
 */
final class SetUserTimezoneSubscriber implements EventSubscriberInterface
{
    private string $default_timezone;
    private \Symfony\Bundle\SecurityBundle\Security $security;

    public function __construct(string $timezone, \Symfony\Bundle\SecurityBundle\Security $security)
    {
        $this->default_timezone = $timezone;
        $this->security = $security;
    }

    public function setTimeZone(ControllerEvent $event): void
    {
        $timezone = null;

        //Check if the user has set a timezone
        $user = $this->security->getUser();
        if ($user instanceof User && !empty($user->getTimezone())) {
            $timezone = $user->getTimezone();
        }

        //Fill with default value if needed
        if (null === $timezone && !empty($this->default_timezone)) {
            $timezone = $this->default_timezone;
        }

        //If timezone was configured anywhere set it, otherwise just use the one from php.ini
        if (null !== $timezone) {
            date_default_timezone_set($timezone);
        }
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
        //Set the timezone shortly before executing the controller
        return [
            KernelEvents::CONTROLLER => 'setTimeZone',
        ];
    }
}
