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

use App\Entity\UserSystem\U2FKey;
use App\Entity\UserSystem\User;
use App\Events\SecurityEvent;
use App\Events\SecurityEvents;
use Doctrine\ORM\EntityManagerInterface;
use R\U2FTwoFactorBundle\Event\RegisterEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class U2FRegistrationSubscriber implements EventSubscriberInterface
{
    private $em;

    private $demo_mode;
    private $flashBag;
    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    /** @var EventDispatcher */
    private $eventDispatcher;

    public function __construct(UrlGeneratorInterface $router, EntityManagerInterface $entityManager, FlashBagInterface $flashBag, EventDispatcherInterface $eventDispatcher, bool $demo_mode)
    {
        $this->router = $router;
        $this->em = $entityManager;
        $this->demo_mode = $demo_mode;
        $this->flashBag = $flashBag;
        $this->eventDispatcher = $eventDispatcher;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'r_u2f_two_factor.register' => 'onRegister',
        ];
    }

    public function onRegister(RegisterEvent $event): void
    {
        //Skip adding of U2F key on demo mode
        if (! $this->demo_mode) {
            $user = $event->getUser();
            if (! $user instanceof User) {
                throw new \InvalidArgumentException('Only User objects can be registered for U2F!');
            }

            $registration = $event->getRegistration();
            $newKey = new U2FKey();
            $newKey->fromRegistrationData($registration);
            $newKey->setUser($user);
            $newKey->setName($event->getKeyName());

            // persist the new key
            $this->em->persist($newKey);
            $this->em->flush();
            $this->flashBag->add('success', 'tfa_u2f.key_added_successful');

            $security_event = new SecurityEvent($user);
            $this->eventDispatcher->dispatch($security_event, SecurityEvents::U2F_ADDED);
        }

        // generate new response, here we redirect the user to the fos user
        // profile
        $response = new RedirectResponse($this->router->generate('user_settings'));
        $event->setResponse($response);
    }
}
