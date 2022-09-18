<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\EventSubscriber\LogSystem;

use App\Entity\LogSystem\UserLogoutLogEntry;
use App\Entity\UserSystem\User;
use App\Services\LogSystem\EventLogger;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * This handler logs to event log, if a user logs out.
 */
class LogoutLoggerListener
{
    protected EventLogger $logger;
    protected bool $gpdr_compliance;

    public function __construct(EventLogger $logger, bool $gpdr_compliance)
    {
        $this->logger = $logger;
        $this->gpdr_compliance = $gpdr_compliance;
    }

    public function __invoke(LogoutEvent $event)
    {
        $request = $event->getRequest();
        $token = $event->getToken();

        if (null === $token) {
            return;
        }

        $log = new UserLogoutLogEntry($request->getClientIp(), $this->gpdr_compliance);
        $user = $token->getUser();
        if ($user instanceof User) {
            $log->setTargetElement($user);
        }

        $this->logger->logAndFlush($log);
    }
}
