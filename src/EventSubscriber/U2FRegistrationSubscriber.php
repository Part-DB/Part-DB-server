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

use App\Entity\UserSystem\U2FKey;
use Doctrine\ORM\EntityManagerInterface;
use R\U2FTwoFactorBundle\Event\RegisterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class U2FRegistrationSubscriber implements EventSubscriberInterface
{
    protected $em;

    protected $demo_mode;
    protected $flashBag;
    /** @var UrlGeneratorInterface */
    private $router;

    public function __construct(UrlGeneratorInterface $router, EntityManagerInterface $entityManager, FlashBagInterface $flashBag, bool $demo_mode)
    {
        $this->router = $router;
        $this->em = $entityManager;
        $this->demo_mode = $demo_mode;
        $this->flashBag = $flashBag;
    }

    /** @return string[] **/
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
            $registration = $event->getRegistration();
            $newKey = new U2FKey();
            $newKey->fromRegistrationData($registration);
            $newKey->setUser($user);
            $newKey->setName($event->getKeyName());

            // persist the new key
            $this->em->persist($newKey);
            $this->em->flush();
            $this->flashBag->add('success', 'tfa_u2f.key_added_successful');
        }

        // generate new response, here we redirect the user to the fos user
        // profile
        $response = new RedirectResponse($this->router->generate('user_settings'));
        $event->setResponse($response);
    }
}
