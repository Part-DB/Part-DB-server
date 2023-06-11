<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\EventSubscriber\UserSystem;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\UserSystem\User;
use App\Services\LogSystem\EventCommentHelper;
use App\Services\UserSystem\PermissionSchemaUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * The purpose of this event subscriber is to check if the permission schema of the current user is up-to-date and upgrade it automatically if needed.
 */
class UpgradePermissionsSchemaSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security, private readonly PermissionSchemaUpdater $permissionSchemaUpdater, private readonly EntityManagerInterface $entityManager, private readonly EventCommentHelper $eventCommentHelper)
    {
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            //Retrieve anonymous user
            $user = $this->entityManager->getRepository(User::class)->getAnonymousUser();
        }

        /** @var Session $session */
        $session = $event->getRequest()->getSession();
        $flashBag = $session->getFlashBag();

        if ($this->permissionSchemaUpdater->isSchemaUpdateNeeded($user)) {
            $this->eventCommentHelper->setMessage('Automatic permission schema update');
            $this->permissionSchemaUpdater->userUpgradeSchemaRecursively($user);
            $this->entityManager->flush();
            $flashBag->add('notice', 'user.permissions_schema_updated');
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onRequest'];
    }
}
