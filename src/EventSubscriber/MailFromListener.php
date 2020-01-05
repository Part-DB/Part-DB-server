<?php

declare(strict_types=1);

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

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class MailFromListener implements EventSubscriberInterface
{
    protected $email;
    protected $name;

    public function __construct(string $email, string $name)
    {
        $this->email = $email;
        $this->name = $name;
    }

    public function onMessage(MessageEvent $event): void
    {
        $address = new Address($this->email, $this->name);
        $event->getEnvelope()->setSender($address);
        $email = $event->getMessage();
        if ($email instanceof Email) {
            $email->from($address);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            // should be the last one to allow header changes by other listeners first
            MessageEvent::class => ['onMessage'],
        ];
    }
}
