<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\UserSystem\TFA;

use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticator;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsDecorator(GoogleAuthenticatorInterface::class)]
class DecoratedGoogleAuthenticator implements GoogleAuthenticatorInterface
{

    public function __construct(
        #[AutowireDecorated]
        private GoogleAuthenticatorInterface $inner,
        private RequestStack $requestStack)
    {

    }

    public function checkCode(TwoFactorInterface $user, string $code): bool
    {
        return $this->inner->checkCode($user, $code);
    }

    public function getQRContent(TwoFactorInterface $user): string
    {
        $qr_content = $this->inner->getQRContent($user);

        //Replace $$DOMAIN$$ with the current domain
        $request = $this->requestStack->getCurrentRequest();

        //If no request is available, just put "Part-DB" as domain
        $domain = "Part-DB";

        if ($request !== null) {
            $domain = $request->getHttpHost();
        }

        //Domain must be url encoded
        $domain = urlencode($domain);

        return str_replace(urlencode('$$DOMAIN$$'), $domain, $qr_content);
    }

    public function generateSecret(): string
    {
        return $this->inner->generateSecret();
    }
}