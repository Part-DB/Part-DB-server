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

namespace App\Services\UserSystem;

use App\Entity\UserSystem\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordResetManager
{
    protected MailerInterface $mailer;
    protected EntityManagerInterface $em;
    protected PasswordHasherInterface $passwordEncoder;
    protected TranslatorInterface $translator;
    protected UserPasswordHasherInterface $userPasswordEncoder;

    public function __construct(MailerInterface $mailer, EntityManagerInterface $em,
                                TranslatorInterface $translator, UserPasswordHasherInterface $userPasswordEncoder,
                                PasswordHasherFactoryInterface $encoderFactory)
    {
        $this->em = $em;
        $this->mailer = $mailer;
        $this->passwordEncoder = $encoderFactory->getPasswordHasher(User::class);
        $this->translator = $translator;
        $this->userPasswordEncoder = $userPasswordEncoder;
    }

    public function request(string $name_or_email): void
    {
        $repo = $this->em->getRepository(User::class);

        //Try to find a user by the given string
        $user = $repo->findByEmailOrName($name_or_email);
        //Do nothing if no user was found
        if (null === $user) {
            return;
        }

        $unencrypted_token = md5(random_bytes(32));
        $user->setPwResetToken($this->passwordEncoder->hash($unencrypted_token));

        //Determine the expiration datetime of
        $expiration_date = new DateTime();
        $expiration_date->add(date_interval_create_from_date_string('1 day'));
        $user->setPwResetExpires($expiration_date);

        if (!empty($user->getEmail())) {
            $address = new Address($user->getEmail(), $user->getFullName());
            $mail = new TemplatedEmail();
            $mail->to($address);
            $mail->subject($this->translator->trans('pw_reset.email.subject'));
            $mail->htmlTemplate('mail/pw_reset.html.twig');
            $mail->context([
                'expiration_date' => $expiration_date,
                'token' => $unencrypted_token,
                'user' => $user,
            ]);

            //Send email
            $this->mailer->send($mail);
        }

        //Save changes to DB
        $this->em->flush();
    }

    /**
     * Sets the new password of the user with the given name, if the token is valid.
     *
     * @param string $username     The name of the user, which password should be reset
     * @param string $token        The token that should be used to reset the password
     * @param string $new_password The new password that should be applied to user
     *
     * @return bool Returns true, if the new password was applied. False, if either the username is unknown or the
     *              token is invalid or expired.
     */
    public function setNewPassword(string $username, string $token, string $new_password): bool
    {
        //Try to find the user
        $repo = $this->em->getRepository(User::class);
        /** @var User|null $user */
        $user = $repo->findOneBy(['name' => $username]);

        //If no user matching the name, show an error message
        if (null === $user) {
            return false;
        }

        //Check if token is expired yet
        if ($user->getPwResetExpires() < new DateTime()) {
            return false;
        }

        //Check if token is valid
        if (!$this->passwordEncoder->verify($user->getPwResetToken(), $token)) {
            return false;
        }

        //When everything was valid, apply the new password
        $user->setPassword($this->userPasswordEncoder->hashPassword($user, $new_password));

        //Remove token
        $user->setPwResetToken(null);
        $user->setPwResetExpires(new DateTime());

        //Save to DB
        $this->em->flush();

        return true;
    }
}
