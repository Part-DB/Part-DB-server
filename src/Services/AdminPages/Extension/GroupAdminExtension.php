<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\AdminPages\Extension;

use App\Entity\UserSystem\Group;
use App\Services\AdminPages\AbstractAdminPageExtension;
use App\Services\UserSystem\PermissionPresetsHelper;
use App\Services\UserSystem\PermissionSchemaUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class GroupAdminExtension extends AbstractAdminPageExtension
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authChecker,
        private readonly RequestStack $requestStack,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PermissionPresetsHelper $permissionPresetsHelper,
        private readonly PermissionSchemaUpdater $permissionSchemaUpdater,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function beforeEdit(object $entity, Request $request): ?Response
    {
        if (!$entity instanceof Group) {
            throw new \InvalidArgumentException('Expected ' . Group::class . ', got ' . $entity::class . '.');
        }

        $this->requireFullAuth();
        $this->permissionSchemaUpdater->groupUpgradeSchemaRecursively($entity);

        if ($request->request->has('permission_preset')) {
            if (!$this->authChecker->isGranted('edit_permissions', $entity)) {
                throw new AccessDeniedException();
            }
            if ($this->csrfTokenManager->isTokenValid(new CsrfToken('group'.$entity->getID(), (string) $request->request->get('_token')))) {
                $this->permissionPresetsHelper->applyPreset($entity, (string) $request->request->get('permission_preset'));
                $this->em->flush();
                /** @var Session $session */
                $session = $this->requestStack->getSession();
                $session->getFlashBag()->add('success', 'user.edit.permission_success');
            } else {
                /** @var Session $session */
                $session = $this->requestStack->getSession();
                $session->getFlashBag()->add('danger', 'csfr_invalid');
            }

            return new RedirectResponse($this->urlGenerator->generate('group_edit', ['id' => $entity->getID()]));
        }

        return null;
    }

    public function beforeNew(Request $request): ?Response
    {
        $this->requireFullAuth();

        return null;
    }

    public function beforeDelete(object $entity, Request $request): ?Response
    {
        $this->requireFullAuth();

        return null;
    }

    public function checkDelete(object $entity): bool
    {
        if (!$entity instanceof Group) {
            throw new \InvalidArgumentException('Expected ' . Group::class . ', got ' . $entity::class . '.');
        }

        if ($entity->getUsers()->count() > 0) {
            /** @var Session $session */
            $session = $this->requestStack->getSession();
            $session->getFlashBag()->add('error', 'entity.delete.must_not_contain_users');

            return false;
        }

        return true;
    }

    private function requireFullAuth(): void
    {
        if (!$this->authChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new AccessDeniedException('Full authentication required.');
        }
    }
}
