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

use App\Entity\UserSystem\U2FKey;
use App\Entity\UserSystem\User;
use App\Events\SecurityEvent;
use App\Events\SecurityEvents;
use App\Form\TFAGoogleSettingsType;
use App\Form\UserSettingsType;
use App\Services\TFA\BackupCodeManager;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;

/**
 * @Route("/user")
 */
class UserSettingsController extends AbstractController
{
    protected $demo_mode;
    /**
     * @var EventDispatcher|EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function __construct(bool $demo_mode, EventDispatcherInterface $eventDispatcher)
    {
        $this->demo_mode = $demo_mode;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @Route("/2fa_backup_codes", name="show_backup_codes")
     */
    public function showBackupCodes()
    {
        $user = $this->getUser();

        //When user change its settings, he should be logged  in fully.
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$user instanceof User) {
            return new RuntimeException('This controller only works only for Part-DB User objects!');
        }

        if (empty($user->getBackupCodes())) {
            $this->addFlash('error', 'tfa_backup.no_codes_enabled');

            throw new RuntimeException('You do not have any backup codes enabled, therefore you can not view them!');
        }

        return $this->render('Users/backup_codes.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * @Route("/u2f_delete", name="u2f_delete", methods={"DELETE"})
     */
    public function removeU2FToken(Request $request, EntityManagerInterface $entityManager, BackupCodeManager $backupCodeManager): RedirectResponse
    {
        if ($this->demo_mode) {
            throw new RuntimeException('You can not do 2FA things in demo mode');
        }

        $user = $this->getUser();

        //When user change its settings, he should be logged  in fully.
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$user instanceof User) {
            throw new RuntimeException('This controller only works only for Part-DB User objects!');
        }

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            if ($request->request->has('key_id')) {
                $key_id = $request->request->get('key_id');
                $key_repo = $entityManager->getRepository(U2FKey::class);
                /** @var U2FKey|null $u2f */
                $u2f = $key_repo->find($key_id);
                if (null === $u2f) {
                    $this->addFlash('danger', 'tfa_u2f.u2f_delete.not_existing');

                    throw new RuntimeException('Key not existing!');
                }

                //User can only delete its own U2F keys
                if ($u2f->getUser() !== $user) {
                    $this->addFlash('danger', 'tfa_u2f.u2f_delete.access_denied');

                    throw new RuntimeException('You can only delete your own U2F keys!');
                }

                $backupCodeManager->disableBackupCodesIfUnused($user);
                $entityManager->remove($u2f);
                $entityManager->flush();
                $this->addFlash('success', 'tfa.u2f.u2f_delete.success');

                $security_event = new SecurityEvent($user);
                $this->eventDispatcher->dispatch($security_event, SecurityEvents::U2F_REMOVED);
            }
        } else {
            $this->addFlash('error', 'csfr_invalid');
        }

        return $this->redirectToRoute('user_settings');
    }

    /**
     * @Route("/invalidate_trustedDevices", name="tfa_trustedDevices_invalidate", methods={"DELETE"})
     *
     * @return RuntimeException|RedirectResponse
     */
    public function resetTrustedDevices(Request $request, EntityManagerInterface $entityManager)
    {
        if ($this->demo_mode) {
            throw new RuntimeException('You can not do 2FA things in demo mode');
        }

        $user = $this->getUser();

        //When user change its settings, he should be logged  in fully.
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$user instanceof User) {
            return new RuntimeException('This controller only works only for Part-DB User objects!');
        }

        if ($this->isCsrfTokenValid('devices_reset'.$user->getId(), $request->request->get('_token'))) {
            $user->invalidateTrustedDeviceTokens();
            $entityManager->flush();
            $this->addFlash('success', 'tfa_trustedDevice.invalidate.success');

            $security_event = new SecurityEvent($user);
            $this->eventDispatcher->dispatch($security_event, SecurityEvents::TRUSTED_DEVICE_RESET);
        } else {
            $this->addFlash('error', 'csfr_invalid');
        }

        return $this->redirectToRoute('user_settings');
    }

    /**
     * @Route("/settings", name="user_settings")
     *
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function userSettings(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder, GoogleAuthenticator $googleAuthenticator, BackupCodeManager $backupCodeManager)
    {
        /** @var User */
        $user = $this->getUser();

        $page_need_reload = false;

        //When user change its settings, he should be logged  in fully.
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if (!$user instanceof User) {
            throw new RuntimeException('This controller only works only for Part-DB User objects!');
        }

        $security_event = new SecurityEvent($user);

        /***************************
         * User settings form
         ***************************/

        $form = $this->createForm(UserSettingsType::class, $user);

        $form->handleRequest($request);

        if (!$this->demo_mode && $form->isSubmitted() && $form->isValid()) {
            //Check if user theme setting has changed
            if ($user->getTheme() !== $em->getUnitOfWork()->getOriginalEntityData($user)['theme']) {
                $page_need_reload = true;
            }

            $em->flush();
            $this->addFlash('success', 'user.settings.saved_flash');
        }

        /*****************************
         * Password change form
         ****************************/

        $pw_form = $this->createFormBuilder()
            //Username field for autocomplete
            ->add('username', TextType::class, [
                'data' => $user->getName(),
                'attr' => [
                    'autocomplete' => 'username',
                ],
                'disabled' => true,
                'row_attr' => [
                    'class' => 'd-none',
                ],
            ])
            ->add('old_password', PasswordType::class, [
                'label' => 'user.settings.pw_old.label',
                'disabled' => $this->demo_mode,
                'attr' => [
                    'autocomplete' => 'current-password',
                ],
                'constraints' => [new UserPassword()],
            ]) //This constraint checks, if the current user pw was inputted.
            ->add('new_password', RepeatedType::class, [
                'disabled' => $this->demo_mode,
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'user.settings.pw_new.label',
                ],
                'second_options' => [
                    'label' => 'user.settings.pw_confirm.label',
                ],
                'invalid_message' => 'password_must_match',
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'constraints' => [new Length([
                    'min' => 6,
                    'max' => 128,
                ])],
            ])
            ->add('submit', SubmitType::class, ['label' => 'save'])
            ->getForm();

        $pw_form->handleRequest($request);

        //Check if password if everything was correct, then save it to User and DB
        if (!$this->demo_mode && $pw_form->isSubmitted() && $pw_form->isValid()) {
            $password = $passwordEncoder->encodePassword($user, $pw_form['new_password']->getData());
            $user->setPassword($password);

            //After the change reset the password change needed setting
            $user->setNeedPwChange(false);

            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'user.settings.pw_changed_flash');
            $this->eventDispatcher->dispatch($security_event, SecurityEvents::PASSWORD_CHANGED);
        }

        //Handle 2FA things
        $google_form = $this->createForm(TFAGoogleSettingsType::class, $user);
        $google_enabled = $user->isGoogleAuthenticatorEnabled();
        if (!$google_enabled && !$form->isSubmitted()) {
            $user->setGoogleAuthenticatorSecret($googleAuthenticator->generateSecret());
            $google_form->get('googleAuthenticatorSecret')->setData($user->getGoogleAuthenticatorSecret());
        }
        $google_form->handleRequest($request);

        if (!$this->demo_mode && $google_form->isSubmitted() && $google_form->isValid()) {
            if (!$google_enabled) {
                //Save 2FA settings (save secrets)
                $user->setGoogleAuthenticatorSecret($google_form->get('googleAuthenticatorSecret')->getData());
                $backupCodeManager->enableBackupCodes($user);

                $em->flush();
                $this->addFlash('success', 'user.settings.2fa.google.activated');

                $this->eventDispatcher->dispatch($security_event, SecurityEvents::GOOGLE_ENABLED);

                return $this->redirectToRoute('user_settings');
            }

            //Remove secret to disable google authenticator
            $user->setGoogleAuthenticatorSecret(null);
            $backupCodeManager->disableBackupCodesIfUnused($user);
            $em->flush();
            $this->addFlash('success', 'user.settings.2fa.google.disabled');
            $this->eventDispatcher->dispatch($security_event, SecurityEvents::GOOGLE_DISABLED);

            return $this->redirectToRoute('user_settings');
        }

        $backup_form = $this->get('form.factory')->createNamedBuilder('backup_codes')->add('reset_codes', SubmitType::class, [
            'label' => 'tfa_backup.regenerate_codes',
            'attr' => [
                'class' => 'btn-danger',
            ],
            'disabled' => empty($user->getBackupCodes()),
        ])->getForm();

        $backup_form->handleRequest($request);
        if (!$this->demo_mode && $backup_form->isSubmitted() && $backup_form->isValid()) {
            $backupCodeManager->regenerateBackupCodes($user);
            $em->flush();
            $this->addFlash('success', 'user.settings.2fa.backup_codes.regenerated');
            $this->eventDispatcher->dispatch($security_event, SecurityEvents::BACKUP_KEYS_RESET);
        }

        /******************************
         * Output both forms
         *****************************/

        return $this->render('Users/user_settings.html.twig', [
            'user' => $user,
            'settings_form' => $form->createView(),
            'pw_form' => $pw_form->createView(),
            'page_need_reload' => $page_need_reload,

            'google_form' => $google_form->createView(),
            'backup_form' => $backup_form->createView(),
            'tfa_google' => [
                'enabled' => $google_enabled,
                'qrContent' => $googleAuthenticator->getQRContent($user),
                'secret' => $user->getGoogleAuthenticatorSecret(),
                'username' => $user->getGoogleAuthenticatorUsername(),
            ],
        ]);
    }
}
