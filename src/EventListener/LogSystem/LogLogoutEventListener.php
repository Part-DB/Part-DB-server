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

namespace App\EventListener\LogSystem;

use App\Entity\LogSystem\UserLogoutLogEntry;
use App\Entity\UserSystem\User;
use App\Services\LogSystem\EventLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * This handler logs to event log, if a user logs out.
 */
#[AsEventListener]
final class LogLogoutEventListener
{
    public function __construct(private EventLogger $logger, private readonly bool $gdpr_compliance)
    {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $token = $event->getToken();

        if (!$token instanceof \Symfony\Component\Security\Core\Authentication\Token\TokenInterface) {
            return;
        }

        $log = new UserLogoutLogEntry($request->getClientIp(), $this->gdpr_compliance);
        $user = $token->getUser();
        if ($user instanceof User) {
            $log->setTargetElement($user);
        }

        $this->logger->logAndFlush($log);
    }

}
