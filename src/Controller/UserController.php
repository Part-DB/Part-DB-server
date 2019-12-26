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

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\UserAttachment;
use App\Entity\UserSystem\User;
use App\Form\Permissions\PermissionsType;
use App\Form\TFAGoogleSettingsType;
use App\Form\UserAdminForm;
use App\Form\UserSettingsType;
use App\Services\EntityExporter;
use App\Services\EntityImporter;
use App\Services\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticator;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\Length;

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

    /**
     * @Route("/{id}/edit", requirements={"id"="\d+"}, name="user_edit")
     * @Route("/{id}/", requirements={"id"="\d+"})
     */
    public function edit(User $entity, Request $request, EntityManagerInterface $em)
    {
        return $this->_edit($entity, $request, $em);
    }

    /**
     * @Route("/new", name="user_new")
     * @Route("/")
     *
     * @return Response
     */
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer)
    {
        return $this->_new($request, $em, $importer);
    }

    /**
     * @Route("/{id}", name="user_delete", methods={"DELETE"})
     */
    public function delete(Request $request, User $entity, StructuralElementRecursionHelper $recursionHelper)
    {
        if (User::ID_ANONYMOUS === $entity->getID()) {
            throw new \InvalidArgumentException('You can not delete the anonymous user! It is needed for permission checking without a logged in user');
        }

        return $this->_delete($request, $entity, $recursionHelper);
    }

    /**
     * @Route("/export", name="user_export_all")
     *
     * @param SerializerInterface $serializer
     *
     * @return Response
     */
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request)
    {
        return $this->_exportAll($em, $exporter, $request);
    }

    /**
     * @Route("/{id}/export", name="user_export")
     *
     * @param AttachmentType $entity
     *
     * @return Response
     */
    public function exportEntity(User $entity, EntityExporter $exporter, Request $request)
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }

    /**
     * @Route("/info", name="user_info_self")
     * @Route("/{id}/info")
     */
    public function userInfo(?User $user, Packages $packages)
    {
        //If no user id was passed, then we show info about the current user
        if (null === $user) {
            $user = $this->getUser();

        } else {
            //Else we must check, if the current user is allowed to access $user
            $this->denyAccessUnlessGranted('read', $user);
        }

        if ($this->getParameter('use_gravatar')) {
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
        ]);
    }

    /**
     * @Route("/settings", name="user_settings")
     */
    public function userSettings(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $passwordEncoder, GoogleAuthenticator $googleAuthenticator)
    {
        /**
         * @var User
         */
        $user = $this->getUser();

        $page_need_reload = false;

        if (!$user instanceof User) {
            return new \RuntimeException('This controller only works only for Part-DB User objects!');
        }

        //When user change its settings, he should be logged  in fully.
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /***************************
         * User settings form
         ***************************/

        $form = $this->createForm(UserSettingsType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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

        $demo_mode = $this->getParameter('demo_mode');

        $pw_form = $this->createFormBuilder()
            //Username field for autocomplete
            ->add('username', TextType::class, [
                'data' => $user->getName(),
                'attr' => ['autocomplete' => 'username'],
                'disabled' => true,
                'row_attr' => ['class' => 'd-none']
            ])
            ->add('old_password', PasswordType::class, [
                'label' => 'user.settings.pw_old.label',
                'disabled' => $demo_mode,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [new UserPassword()], ]) //This constraint checks, if the current user pw was inputted.
            ->add('new_password', RepeatedType::class, [
                'disabled' => $demo_mode,
                'type' => PasswordType::class,
                'first_options' => ['label' => 'user.settings.pw_new.label'],
                'second_options' => ['label' => 'user.settings.pw_confirm.label'],
                'invalid_message' => 'password_must_match',
                'options' => [
                    'attr' => ['autocomplete' => 'new-password']
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
        if ($pw_form->isSubmitted() && $pw_form->isValid()) {
            $password = $passwordEncoder->encodePassword($user, $pw_form['new_password']->getData());
            $user->setPassword($password);

            //After the change reset the password change needed setting
            $user->setNeedPwChange(false);

            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'user.settings.pw_changed_flash');
        }

        //Handle 2FA things
        $google_form = $this->createForm(TFAGoogleSettingsType::class, $user);
        $google_enabled = $user->isGoogleAuthenticatorEnabled();
        if (!$form->isSubmitted() && !$google_enabled) {
            $user->setGoogleAuthenticatorSecret($googleAuthenticator->generateSecret());
            $google_form->get('googleAuthenticatorSecret')->setData($user->getGoogleAuthenticatorSecret());
        }
        $google_form->handleRequest($request);

        if($google_form->isSubmitted() && $google_form->isValid()) {
            if (!$google_enabled) {
                //Save 2FA settings (save secrets)
                $user->setGoogleAuthenticatorSecret($google_form->get('googleAuthenticatorSecret')->getData());
                $em->flush();
                $this->addFlash('success', 'user.settings.2fa.google.activated');
                return $this->redirectToRoute('user_settings');
            } elseif ($google_enabled) {
                //Remove secret to disable google authenticator
                $user->setGoogleAuthenticatorSecret(null);
                $em->flush();
                $this->addFlash('success', 'user.settings.2fa.google.disabled');
                return $this->redirectToRoute('user_settings');
            }
        }


        /******************************
         * Output both forms
         *****************************/

        return $this->render('Users/user_settings.html.twig', [
            'settings_form' => $form->createView(),
            'pw_form' => $pw_form->createView(),
            'page_need_reload' => $page_need_reload,

            'google_form' => $google_form->createView(),
            'tfa_google' => [
                'enabled' => $google_enabled,
                'qrContent' => $googleAuthenticator->getQRContent($user),
                'secret' => $user->getGoogleAuthenticatorSecret(),
                'username' => $user->getGoogleAuthenticatorUsername()
            ]
        ]);
    }

    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param string $email The email address
     * @param string $s     Size in pixels, defaults to 80px [ 1 - 2048 ]
     * @param string $d     Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
     * @param string $r     Maximum rating (inclusive) [ g | pg | r | x ]
     * @param bool   $img   True to return a complete IMG tag False for just the URL
     * @param array  $atts  Optional, additional key/value attributes to include in the IMG tag
     *
     * @return string containing either just a URL or a complete image tag
     * @source https://gravatar.com/site/implement/images/php/
     */
    public function getGravatar(?string $email, int $s = 80, string $d = 'mm', string $r = 'g', bool $img = false, array $atts = [])
    {
        if (null === $email) {
            return '';
        }

        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=$s&d=$d&r=$r";
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
