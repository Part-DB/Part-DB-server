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

use App\Entity\UserSystem\User;
use App\Events\SecurityEvent;
use App\Events\SecurityEvents;
use App\Services\AdminPages\AbstractAdminPageExtension;
use App\Services\UserSystem\PermissionPresetsHelper;
use App\Services\UserSystem\PermissionSchemaUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UserAdminExtension extends AbstractAdminPageExtension
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authChecker,
        private readonly RequestStack $requestStack,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PermissionPresetsHelper $permissionPresetsHelper,
        private readonly PermissionSchemaUpdater $permissionSchemaUpdater,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
    ) {
    }

    public function beforeEdit(object $entity, Request $request): ?Response
    {
        if (!$entity instanceof User) {
            throw new \InvalidArgumentException('Expected ' . User::class . ', got ' . $entity::class . '.');
        }

        $this->requireFullAuth();
        $this->permissionSchemaUpdater->userUpgradeSchemaRecursively($entity);

        // Handle 2FA reset
        if ($request->request->has('reset_2fa')) {
            if (!$this->authChecker->isGranted('set_password', $entity)) {
                throw new AccessDeniedException();
            }
            if ($this->csrfTokenManager->isTokenValid(new CsrfToken('reset_2fa'.$entity->getID(), (string) $request->request->get('_token')))) {
                $entity->setGoogleAuthenticatorSecret(null);
                $entity->setBackupCodes([]);
                foreach ($entity->getLegacyU2FKeys() as $key) {
                    $this->em->remove($key);
                }
                foreach ($entity->getWebauthnKeys() as $key) {
                    $this->em->remove($key);
                }
                $entity->invalidateTrustedDeviceTokens();
                $this->em->flush();

                $event = new SecurityEvent($entity);
                $this->eventDispatcher->dispatch($event, SecurityEvents::TFA_ADMIN_RESET);

                /** @var Session $session */
                $session = $this->requestStack->getSession();
                $session->getFlashBag()->add('success', 'user.edit.reset_success');
            } else {
                /** @var Session $session */
                $session = $this->requestStack->getSession();
                $session->getFlashBag()->add('error', 'csfr_invalid');
            }
        }

        // Handle permission preset
        if ($request->request->has('permission_preset')) {
            if (!$this->authChecker->isGranted('edit_permissions', $entity)) {
                throw new AccessDeniedException();
            }
            if ($this->csrfTokenManager->isTokenValid(new CsrfToken('reset_2fa'.$entity->getID(), (string) $request->request->get('_token')))) {
                $this->permissionPresetsHelper->applyPreset($entity, (string) $request->request->get('permission_preset'));
                $errors = $this->validator->validate($entity);
                if (count($errors) > 0) {
                    /** @var Session $session */
                    $session = $this->requestStack->getSession();
                    $session->getFlashBag()->add('error', 'validator.noLockout');
                    $this->em->refresh($entity);
                } else {
                    $this->em->flush();
                    /** @var Session $session */
                    $session = $this->requestStack->getSession();
                    $session->getFlashBag()->add('success', 'user.edit.permission_success');

                    return new RedirectResponse($this->urlGenerator->generate('user_edit', ['id' => $entity->getID()]));
                }
            } else {
                /** @var Session $session */
                $session = $this->requestStack->getSession();
                $session->getFlashBag()->add('error', 'csfr_invalid');
            }
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
        if (!$entity instanceof User) {
            throw new \InvalidArgumentException('Expected ' . User::class . ', got ' . $entity::class . '.');
        }

        $this->requireFullAuth();

        if (User::ID_ANONYMOUS === $entity->getID()) {
            throw new \LogicException('You can not delete the anonymous user! It is needed for permission checking without a logged in user');
        }
        if ($entity === $this->security->getUser()) {
            throw new \LogicException('You can not delete your own user account!');
        }

        return null;
    }

    public function onEditFormValid(FormInterface $form, object $entity): bool
    {
        if (!$entity instanceof User) {
            throw new \InvalidArgumentException('Expected ' . User::class . ', got ' . $entity::class . '.');
        }

        if (!empty($form['new_password']->getData())) {
            $entity->setPassword($this->passwordHasher->hashPassword($entity, $form['new_password']->getData()));
            $entity->setNeedPwChange(true);

            $event = new SecurityEvent($entity);
            $this->eventDispatcher->dispatch($event, SecurityEvents::PASSWORD_CHANGED);
        }

        return true;
    }

    public function onNewFormValid(FormInterface $form, object $entity): bool
    {
        if (!$entity instanceof User) {
            throw new \InvalidArgumentException('Expected ' . User::class . ', got ' . $entity::class . '.');
        }

        if (!empty($form['new_password']->getData())) {
            $entity->setPassword($this->passwordHasher->hashPassword($entity, $form['new_password']->getData()));
            $entity->setNeedPwChange(true);
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
