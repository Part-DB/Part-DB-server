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

namespace App\Entity\UserSystem;

use Doctrine\DBAL\Types\Types;
use App\Entity\Base\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;
use Webauthn\PublicKeyCredentialSource as BasePublicKeyCredentialSource;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'webauthn_keys')]
class WebauthnKey extends BasePublicKeyCredentialSource
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected int $id;

    #[ORM\Column(type: Types::STRING)]
    protected string $name;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'webauthn_keys')]
    protected ?User $user = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): WebauthnKey
    {
        $this->name = $name;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): WebauthnKey
    {
        $this->user = $user;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }





    public static function fromRegistration(BasePublicKeyCredentialSource $registration): self
    {
        return new self(
            $registration->getPublicKeyCredentialId(),
            $registration->getType(),
            $registration->getTransports(),
            $registration->getAttestationType(),
            $registration->getTrustPath(),
            $registration->getAaguid(),
            $registration->getCredentialPublicKey(),
            $registration->getUserHandle(),
            $registration->getCounter(),
            $registration->getOtherUI()
        );
    }
}