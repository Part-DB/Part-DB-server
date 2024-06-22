<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Security\TwoFactor;

use App\Entity\UserSystem\WebauthnKey;
use Doctrine\ORM\EntityManagerInterface;
use Jbtronics\TFAWebauthn\Services\UserPublicKeyCredentialSourceRepository;
use Jbtronics\TFAWebauthn\Services\WebauthnProvider;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * This class decorates the Webauthn TwoFactorProvider and adds additional logic which allows us to set a last used date
 * on the used webauthn key, which can be viewed in the user settings.
 */
#[AsDecorator('jbtronics_webauthn_tfa.two_factor_provider')]
class WebauthnKeyLastUseTwoFactorProvider implements TwoFactorProviderInterface
{

    public function __construct(
        #[AutowireDecorated]
        private readonly TwoFactorProviderInterface $decorated,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(service: 'jbtronics_webauthn_tfa.user_public_key_source_repo')]
        private readonly UserPublicKeyCredentialSourceRepository $publicKeyCredentialSourceRepository,
        #[Autowire(service: 'jbtronics_webauthn_tfa.webauthn_provider')]
        private readonly WebauthnProvider $webauthnProvider,
    )
    {
    }

    public function beginAuthentication(AuthenticationContextInterface $context): bool
    {
        return $this->decorated->beginAuthentication($context);
    }

    public function prepareAuthentication(object $user): void
    {
        $this->decorated->prepareAuthentication($user);
    }

    public function validateAuthenticationCode(object $user, string $authenticationCode): bool
    {
        //Try to extract the used webauthn key from the code
        $webauthnKey = $this->getWebauthnKeyFromCode($authenticationCode);

        //Perform the actual validation like normal
        $tmp = $this->decorated->validateAuthenticationCode($user, $authenticationCode);

        //Update the last used date of the webauthn key, if the validation was successful
        if($tmp && $webauthnKey !== null) {
            $webauthnKey->updateLastTimeUsed();
            $this->entityManager->flush();
        }

        return $tmp;
    }

    public function getFormRenderer(): TwoFactorFormRendererInterface
    {
        return $this->decorated->getFormRenderer();
    }

    private function getWebauthnKeyFromCode(string $authenticationCode): ?WebauthnKey
    {
        $publicKeyCredentialLoader = $this->webauthnProvider->getPublicKeyCredentialLoader();

        //Try to load the public key credential from the code
        $publicKeyCredential = $publicKeyCredentialLoader->load($authenticationCode);

        //Find the credential source for the given credential id
        $publicKeyCredentialSource = $this->publicKeyCredentialSourceRepository->findOneByCredentialId($publicKeyCredential->rawId);

        //If the credential source is not an instance of WebauthnKey, return null
        if(!($publicKeyCredentialSource instanceof WebauthnKey)) {
            return null;
        }

        return $publicKeyCredentialSource;
    }
}