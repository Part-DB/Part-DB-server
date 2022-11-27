<?php

namespace App\Controller;

use App\Entity\UserSystem\WebauthnKey;
use Doctrine\ORM\EntityManagerInterface;
use Jbtronics\TFAWebauthn\Services\TFAWebauthnRegistrationHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use function Symfony\Component\Translation\t;

class WebauthnKeyRegistrationController extends AbstractController
{
    /**
     * @Route("/webauthn/register", name="webauthn_register")
     */
    public function register(Request $request, TFAWebauthnRegistrationHelper $registrationHelper, EntityManagerInterface $em)
    {
        //When user change its settings, he should be logged  in fully.
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

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
            'security/Webauthn/webauthn_register.html.twig',
            [
                'registrationRequest' => $registrationHelper->generateRegistrationRequestAsJSON(),
            ]
        );
    }
}