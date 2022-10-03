<?php

namespace App\Controller;

use Jbtronics\TFAWebauthn\Services\TFAWebauthnRegistrationHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class WebauthnKeyRegistrationController extends AbstractController
{
    /**
     * @Route("/webauthn/register", name="webauthn_register")
     */
    public function register(Request $request, TFAWebauthnRegistrationHelper $registrationHelper)
    {

        //If form was submitted, check the auth response
        if ($request->getMethod() === 'POST') {
            $webauthnResponse = $request->request->get('_auth_code');

            //Retrieve other data from the form, that you want to store with the key
            $keyName = $request->request->get('keyName');

            //Check the response
            $new_key = $registrationHelper->checkRegistrationResponse($webauthnResponse);

            dump($new_key);

            $this->addFlash('success', 'Key registered successfully');
        }


        return $this->render(
            'Security/U2F/u2f_register.html.twig',
            [
                'registrationRequest' => $registrationHelper->generateRegistrationRequestAsJSON(),
            ]
        );
    }
}