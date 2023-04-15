<?php
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

use App\Entity\UserSystem\User;
use App\Services\LogSystem\EventCommentHelper;
use App\Services\UserSystem\PermissionSchemaUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;

/**
 * The purpose of this event subscriber is to check if the permission schema of the current user is up to date and upgrade it automatically if needed.
 */
class UpgradePermissionsSchemaSubscriber implements EventSubscriberInterface
{
    private Security $security;
    private PermissionSchemaUpdater $permissionSchemaUpdater;
    private EntityManagerInterface $entityManager;
    private EventCommentHelper $eventCommentHelper;

    public function __construct(Security $security, PermissionSchemaUpdater $permissionSchemaUpdater, EntityManagerInterface $entityManager, EventCommentHelper $eventCommentHelper)
    {
        /** @var Session $session */
        $this->security = $security;
        $this->permissionSchemaUpdater = $permissionSchemaUpdater;
        $this->entityManager = $entityManager;
        $this->eventCommentHelper = $eventCommentHelper;
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (null === $user) {
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