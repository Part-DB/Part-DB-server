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
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
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
    protected $entity_class = User::class;
    protected $twig_template = 'AdminPages/UserAdmin.html.twig';
    protected $form_class = UserAdminForm::class;
    protected $route_base = 'user';
    protected $attachment_class = UserAttachment::class;
    //Just define a value here to prevent error. It is not used.
    protected $parameter_class = AbstractParameter::class;

    protected function additionalActionEdit(FormInterface $form, AbstractNamedDBElement $entity): bool
    {
        //Check if we editing a user and if we need to change the password of it
        if ($entity instanceof User && !empty($form['new_password']->getData())) {
            $password = $this->passwordEncoder->encodePassword($entity, $form['new_password']->getData());
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
     * @throws \Exception
     */
    public function edit(User $entity, Request $request, EntityManagerInterface $em, ?string $timestamp = null): Response
    {
        //Handle 2FA disabling

        if ($request->request->has('reset_2fa')) {
            //Check if the admin has the needed permissions
            $this->denyAccessUnlessGranted('set_password', $entity);
            if ($this->isCsrfTokenValid('reset_2fa'.$entity->getId(), $request->request->get('_token'))) {
                //Disable Google authenticator
                $entity->setGoogleAuthenticatorSecret(null);
                $entity->setBackupCodes([]);
                //Remove all U2F keys
                foreach ($entity->getU2FKeys() as $key) {
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

        return $this->_edit($entity, $request, $em, $timestamp);
    }

    protected function additionalActionNew(FormInterface $form, AbstractNamedDBElement $entity): bool
    {
        if ($entity instanceof User && !empty($form['new_password']->getData())) {
            $password = $this->passwordEncoder->encodePassword($entity, $form['new_password']->getData());
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

    protected function deleteCheck(AbstractNamedDBElement $entity): bool
    {
        if ($entity instanceof User) {
            //TODO: Find a better solution
            $this->addFlash('error', 'Currently it is not possible to delete a user, as this would break the log... This will be implemented later...');

            return false;
        }

        return true;
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

        if ($this->getParameter('partdb.users.use_gravatar')) {
            $avatar = $this->getGravatar($user->getEmail(), 200, 'identicon');
        } else {
            $avatar = $packages->getUrl('/img/default_avatar.png');
        }

        //Show permissions to user
        $builder = $this->createFormBuilder()->add('permissions', PermissionsType::class, [
            'mapped' => false,
            'disabled' => true,
            'inherit' => true,
            'data' => $user,
        ]);

        return $this->render('Users/user_info.html.twig', [
            'user' => $user,
            'avatar' => $avatar,
            'form' => $builder->getForm()->createView(),
            'datatable' => $table,
        ]);
    }

    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param string $email The email address
     * @param int    $s     Size in pixels, defaults to 80px [ 1 - 2048 ]
     * @param string $d     Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
     * @param string $r     Maximum rating (inclusive) [ g | pg | r | x ]
     * @param bool   $img   True to return a complete IMG tag False for just the URL
     * @param array  $atts  Optional, additional key/value attributes to include in the IMG tag
     *
     * @return string containing either just a URL or a complete image tag
     * @source https://gravatar.com/site/implement/images/php/
     */
    public function getGravatar(?string $email, int $s = 80, string $d = 'mm', string $r = 'g', bool $img = false, array $atts = []): string
    {
        if (null === $email) {
            return '';
        }

        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=${s}&d=${d}&r=${r}";
        if ($img) {
            $url = '<img src="'.$url.'"';
            foreach ($atts as $key => $val) {
                $url .= ' '.$key.'="'.$val.'"';
            }
            $url .= ' />';
        }

        return $url;
    }
}
