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

namespace App\Controller;

use App\DataTables\LogDataTable;
use App\Entity\Attachments\UserAttachment;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\UserSystem\User;
use App\Events\SecurityEvent;
use App\Events\SecurityEvents;
use App\Form\Permissions\PermissionsType;
use App\Form\UserAdminForm;
use App\Services\ImportExportSystem\EntityExporter;
use App\Services\ImportExportSystem\EntityImporter;
use App\Services\Trees\StructuralElementRecursionHelper;
use App\Services\UserSystem\PermissionPresetsHelper;
use App\Services\UserSystem\PermissionSchemaUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/user")
 * Class UserController
 */
class UserController extends AdminPages\BaseAdminController
{
    protected string $entity_class = User::class;
    protected string $twig_template = 'AdminPages/UserAdmin.html.twig';
    protected string $form_class = UserAdminForm::class;
    protected string $route_base = 'user';
    protected string $attachment_class = UserAttachment::class;
    protected ?string $parameter_class = null;

    protected function additionalActionEdit(FormInterface $form, AbstractNamedDBElement $entity): bool
    {
        //Check if we editing a user and if we need to change the password of it
        if ($entity instanceof User && !empty($form['new_password']->getData())) {
            $password = $this->passwordEncoder->hashPassword($entity, $form['new_password']->getData());
            $entity->setPassword($password);
            //By default the user must change the password afterwards
            $entity->setNeedPwChange(true);

            $event = new SecurityEvent($entity);
            $this->eventDispatcher->dispatch($event, SecurityEvents::PASSWORD_CHANGED);
        }

        return true;
    }

    /**
     * @Route("/{id}/edit/{timestamp}", requirements={"id"="\d+"}, name="user_edit")
     * @Route("/{id}/", requirements={"id"="\d+"})
     *
     * @throws Exception
     */
    public function edit(User $entity, Request $request, EntityManagerInterface $em,  PermissionPresetsHelper $permissionPresetsHelper, PermissionSchemaUpdater $permissionSchemaUpdater, ?string $timestamp = null): Response
    {
        //Do an upgrade of the permission schema if needed (so the user can see the permissions a user get on next request (even if it was not done yet)
        $permissionSchemaUpdater->userUpgradeSchemaRecursively($entity);

        //Handle 2FA disabling
        if ($request->request->has('reset_2fa')) {
            //Check if the admin has the needed permissions
            $this->denyAccessUnlessGranted('set_password', $entity);
            if ($this->isCsrfTokenValid('reset_2fa'.$entity->getId(), $request->request->get('_token'))) {
                //Disable Google authenticator
                $entity->setGoogleAuthenticatorSecret(null);
                $entity->setBackupCodes([]);
                //Remove all U2F keys
                foreach ($entity->getLegacyU2FKeys() as $key) {
                    $em->remove($key);
                }
                foreach ($entity->getWebAuthnKeys() as $key) {
                    $em->remove($key);
                }
                //Invalidate trusted devices
                $entity->invalidateTrustedDeviceTokens();
                $em->flush();

                $event = new SecurityEvent($entity);
                $this->eventDispatcher->dispatch($event, SecurityEvents::TFA_ADMIN_RESET);

                $this->addFlash('success', 'user.edit.reset_success');
            } else {
                $this->addFlash('danger', 'csfr_invalid');
            }
        }

        //Handle permissions presets
        if ($request->request->has('permission_preset')) {
            $this->denyAccessUnlessGranted('edit_permissions', $entity);
            if ($this->isCsrfTokenValid('reset_2fa'.$entity->getId(), $request->request->get('_token'))) {
                $preset = $request->request->get('permission_preset');

                $permissionPresetsHelper->applyPreset($entity, $preset);

                $em->flush();

                $this->addFlash('success', 'user.edit.permission_success');

                //We need to stop the execution here, or our permissions changes will be overwritten by the form values
                return $this->redirectToRoute('user_edit', ['id' => $entity->getID()]);
            } else {
                $this->addFlash('danger', 'csfr_invalid');
            }
        }

        return $this->_edit($entity, $request, $em, $timestamp);
    }

    protected function additionalActionNew(FormInterface $form, AbstractNamedDBElement $entity): bool
    {
        if ($entity instanceof User && !empty($form['new_password']->getData())) {
            $password = $this->passwordEncoder->hashPassword($entity, $form['new_password']->getData());
            $entity->setPassword($password);
            //By default the user must change the password afterwards
            $entity->setNeedPwChange(true);
        }

        return true;
    }

    /**
     * @Route("/new", name="user_new")
     * @Route("/{id}/clone", name="user_clone")
     * @Route("/")
     */
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer, ?User $entity = null): Response
    {
        return $this->_new($request, $em, $importer, $entity);
    }

    /**
     * @Route("/{id}", name="user_delete", methods={"DELETE"}, requirements={"id"="\d+"})
     */
    public function delete(Request $request, User $entity, StructuralElementRecursionHelper $recursionHelper): RedirectResponse
    {
        if (User::ID_ANONYMOUS === $entity->getID()) {
            throw new InvalidArgumentException('You can not delete the anonymous user! It is needed for permission checking without a logged in user');
        }

        return $this->_delete($request, $entity, $recursionHelper);
    }

    /**
     * @Route("/export", name="user_export_all")
     */
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportAll($em, $exporter, $request);
    }

    /**
     * @Route("/{id}/export", name="user_export")
     */
    public function exportEntity(User $entity, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }

    /**
     * @Route("/info", name="user_info_self")
     * @Route("/{id}/info", name="user_info")
     */
    public function userInfo(?User $user, Packages $packages, Request $request, DataTableFactory $dataTableFactory): Response
    {
        //If no user id was passed, then we show info about the current user
        if (null === $user) {
            $tmp = $this->getUser();
            if (!$tmp instanceof User) {
                throw new InvalidArgumentException('Userinfo only works for database users!');
            }
            $user = $tmp;
        } else {
            //Else we must check, if the current user is allowed to access $user
            $this->denyAccessUnlessGranted('read', $user);
        }

        $table = $this->dataTableFactory->createFromType(
            LogDataTable::class,
            [
                'filter_elements' => $user,
                'mode' => 'element_history',
            ],
            ['pageLength' => 10]
        )
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        //Show permissions to user
        $builder = $this->createFormBuilder()->add('permissions', PermissionsType::class, [
            'mapped' => false,
            'disabled' => true,
            'inherit' => true,
            'data' => $user,
        ]);

        return $this->renderForm('users/user_info.html.twig', [
            'user' => $user,
            'form' => $builder->getForm(),
            'datatable' => $table,
        ]);
    }
}
