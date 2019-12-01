<?php
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

use App\Services\PasswordResetManager;
use Doctrine\ORM\EntityManagerInterface;
use Gregwar\CaptchaBundle\Type\CaptchaType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    protected $translator;
    protected $allow_email_pw_reset;

    public function __construct(TranslatorInterface $translator, bool $allow_email_pw_reset)
    {
        $this->translator = $translator;
        $this->allow_email_pw_reset = $allow_email_pw_reset;
    }

    /**
     * @Route("/login", name="login", methods={"GET", "POST"})
     */
    public function login(AuthenticationUtils $authenticationUtils)
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * @Route("/pw_reset/request", name="pw_reset_request")
     */
    public function requestPwReset(PasswordResetManager $passwordReset, Request $request)
    {
        if (!$this->allow_email_pw_reset) {
            throw new AccessDeniedHttpException("The password reset via email is disabled!");
        }

        if ($this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedHttpException("You are already logged in, so you can not reset your password!");
        }

        $builder = $this->createFormBuilder();
        $builder->add('user', TextType::class, [
            'label' => $this->translator->trans('pw_reset.user_or_password'),
            'constraints' => [new NotBlank()]
        ]);
        $builder->add('captcha', CaptchaType::class, [
            'width' => 200,
            'height' => 50,
            'length' => 6,
        ]);
        $builder->add('submit', SubmitType::class, [
            'label' => 'pw_reset.submit'
        ]);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $passwordReset->request($form->getData()['user']);
            $this->addFlash('success', $this->translator->trans('pw_reset.request.success'));
            return $this->redirectToRoute('login');
        }

        return $this->render('security/pw_reset_request.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/pw_reset/new_pw/{user}/{token}", name="pw_reset_new_pw")
     */
    public function pwResetNewPw(PasswordResetManager $passwordReset, Request $request, string $user = null, string $token = null)
    {
        if (!$this->allow_email_pw_reset) {
            throw new AccessDeniedHttpException("The password reset via email is disabled!");
        }

        if ($this->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            throw new AccessDeniedHttpException("You are already logged in, so you can not reset your password!");
        }

        $data = ['username' => $user, 'token' => $token];
        $builder = $this->createFormBuilder($data);
        $builder->add('username', TextType::class, [
            'label' => $this->translator->trans('pw_reset.username')
        ]);
        $builder->add('token', TextType::class, [
            'label' => $this->translator->trans('pw_reset.token')
        ]);
        $builder->add('new_password', RepeatedType::class, [
            'type' => PasswordType::class,
            'first_options' => ['label' => 'user.settings.pw_new.label'],
            'second_options' => ['label' => 'user.settings.pw_confirm.label'],
            'invalid_message' => 'password_must_match',
            'constraints' => [new Length([
                'min' => 6,
                'max' => 128,
            ])],
        ]);

        $builder->add('submit', SubmitType::class, [
            'label' => 'pw_reset.submit'
        ]);

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            //Try to set the new password
            $success = $passwordReset->setNewPassword($data['username'], $data['token'], $data['new_password']);
            if (!$success) {
                $this->addFlash('error', $this->translator->trans('pw_reset.new_pw.error'));
            } else {
                $this->addFlash('success', $this->translator->trans('pw_reset.new_pw.success'));
                return $this->redirectToRoute('login');
            }
        }


        return $this->render('security/pw_reset_new_pw.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/logout", name="logout")
     */
    public function logout()
    {
        throw new \RuntimeException('Will be intercepted before getting here');
    }
}
