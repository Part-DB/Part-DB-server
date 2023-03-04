<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Controller;

use App\Entity\UserSystem\User;
use App\Entity\UserSystem\WebauthnKey;
use Doctrine\ORM\EntityManagerInterface;
use Jbtronics\TFAWebauthn\Services\TFAWebauthnRegistrationHelper;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use function Symfony\Component\Translation\t;

class WebauthnKeyRegistrationController extends AbstractController
{
    private bool $demo_mode;

    public function __construct(bool $demo_mode)
    {
        $this->demo_mode = $demo_mode;
    }

    /**
     * @Route("/webauthn/register", name="webauthn_register")
     */
    public function register(Request $request, TFAWebauthnRegistrationHelper $registrationHelper, EntityManagerInterface $em)
    {
        //When user change its settings, he should be logged  in fully.
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($this->demo_mode) {
            throw new RuntimeException('You can not do 2FA things in demo mode');
        }

        $user = $this->getUser();

        if (!$user instanceof User) {
            throw new RuntimeException('This controller only works only for Part-DB User objects!');
        }

        if ($user->isSamlUser()) {
            throw new RuntimeException('You can not remove U2F keys from SAML users!');
        }

        //If form was submitted, check the auth response
        if ($request->getMethod() === 'POST') {
            $webauthnResponse = $request->request->get('_auth_code');

            //Retrieve other data from the form, that you want to store with the key
            $keyName = $request->request->get('keyName');
            if (empty($keyName)) {
                $keyName = 'Key ' . date('Y-m-d H:i:s');
            }

            //Check the response
            try {
                $new_key = $registrationHelper->checkRegistrationResponse($webauthnResponse);
            } catch (\Exception $exception) {
                $this->addFlash('error', t('tfa_u2f.add_key.registration_error'));
                return $this->redirectToRoute('webauthn_register');
            }

            $keyEntity = WebauthnKey::fromRegistration($new_key);
            $keyEntity->setName($keyName);
            $keyEntity->setUser($this->getUser());

            $em->persist($keyEntity);
            $em->flush();


            $this->addFlash('success', 'Key registered successfully');
            return $this->redirectToRoute('user_settings');
        }


        return $this->render(
            'security/webauthn/webauthn_register.html.twig',
            [
                'registrationRequest' => $registrationHelper->generateRegistrationRequestAsJSON(),
            ]
        );
    }
}