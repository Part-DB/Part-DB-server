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

namespace App\EventSubscriber\LogSystem;

use App\Entity\LogSystem\SecurityEventLogEntry;
use App\Events\SecurityEvent;
use App\Events\SecurityEvents;
use App\Services\LogSystem\EventLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This subscriber writes entries to log if an security related event happens (e.g. the user changes its password).
 */
final class SecurityEventLoggerSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;
    private bool $gpdr_compliant;
    private EventLogger $eventLogger;

    public function __construct(RequestStack $requestStack, EventLogger $eventLogger, bool $gpdr_compliance)
    {
        $this->requestStack = $requestStack;
        $this->gpdr_compliant = $gpdr_compliance;
        $this->eventLogger = $eventLogger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::U2F_ADDED => 'u2f_added',
            SecurityEvents::PASSWORD_CHANGED => 'password_changed',
            SecurityEvents::TRUSTED_DEVICE_RESET => 'trusted_device_reset',
            SecurityEvents::U2F_REMOVED => 'u2f_removed',
            SecurityEvents::BACKUP_KEYS_RESET => 'backup_keys_reset',
            SecurityEvents::PASSWORD_RESET => 'password_reset',
            SecurityEvents::GOOGLE_DISABLED => 'google_disabled',
            SecurityEvents::GOOGLE_ENABLED => 'google_enabled',
            SecurityEvents::TFA_ADMIN_RESET => 'tfa_admin_reset',
        ];
    }

    public function tfa_admin_reset(SecurityEvent $event): void
    {
        $this->addLog(SecurityEvents::TFA_ADMIN_RESET, $event);
    }

    public function google_enabled(SecurityEvent $event): void
    {
        $this->addLog(SecurityEvents::GOOGLE_ENABLED, $event);
    }

    public function google_disabled(SecurityEvent $event): void
    {
        $this->addLog(SecurityEvents::GOOGLE_DISABLED, $event);
    }

    public function password_reset(SecurityEvent $event): void
    {
        $this->addLog(SecurityEvents::PASSWORD_RESET, $event);
    }

    public function backup_keys_reset(SecurityEvent $event): void
    {
        $this->addLog(SecurityEvents::BACKUP_KEYS_RESET, $event);
    }

    public function u2f_removed(SecurityEvent $event): void
    {
        $this->addLog(SecurityEvents::U2F_REMOVED, $event);
    }

    public function u2f_added(SecurityEvent $event): void
    {
        $this->addLog(SecurityEvents::U2F_ADDED, $event);
    }

    public function password_changed(SecurityEvent $event): void
    {
        $this->addLog(SecurityEvents::PASSWORD_CHANGED, $event);
    }

    public function trusted_device_reset(SecurityEvent $event): void
    {
        $this->addLog(SecurityEvents::TRUSTED_DEVICE_RESET, $event);
    }

    private function addLog(string $type, SecurityEvent $event): void
    {
        $anonymize = $this->gpdr_compliant;

        $request = $this->requestStack->getCurrentRequest();
        if (null !== $request) {
            $ip = $request->getClientIp() ?? 'unknown';
        } else {
            $ip = 'Console';
            //Dont try to apply IP filter rules to non numeric string
            $anonymize = false;
        }

        $log = new SecurityEventLogEntry($type, $ip, $anonymize);
        $log->setTargetElement($event->getTargetUser());
        $this->eventLogger->logAndFlush($log);
    }
}
