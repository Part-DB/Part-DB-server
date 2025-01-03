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

namespace App\Entity\UserSystem;

use App\Entity\Contracts\TimeStampableInterface;
use Doctrine\DBAL\Types\Types;
use App\Entity\Base\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;
use Jbtronics\TFAWebauthn\Model\LegacyU2FKeyInterface;
use Symfony\Component\Validator\Constraints\Length;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: 'u2f_keys')]
#[ORM\UniqueConstraint(name: 'user_unique', columns: ['user_id', 'key_handle'])]
class U2FKey implements LegacyU2FKeyInterface, TimeStampableInterface
{
    use TimestampTrait;

    /**
     * We have to restrict the length here, as InnoDB only supports key index with max. 767 Bytes.
     * Max length of keyhandles should be 128. (According to U2F_MAX_KH_SIZE in FIDO example C code).
     *
     *
     * @var string
     **/
    #[ORM\Column(type: Types::STRING, length: 128)]
    #[Length(max: 128)]
    public string $keyHandle = '';

    /**
     * @var string
     **/
    #[ORM\Column(type: Types::STRING)]
    public string $publicKey = '';

    /**
     * @var string
     **/
    #[ORM\Column(type: Types::TEXT)]
    public string $certificate = '';

    /**
     * @var string
     **/
    #[ORM\Column(type: Types::STRING)]
    public string $counter = '0';

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected int $id;

    /**
     * @var string
     **/
    #[ORM\Column(type: Types::STRING)]
    protected string $name = '';

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'u2fKeys')]
    protected ?User $user = null;

    public function getKeyHandle(): string
    {
        return $this->keyHandle;
    }


    public function setKeyHandle($keyHandle): self
    {
        $this->keyHandle = $keyHandle;

        return $this;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function setPublicKey($publicKey): self
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    public function getCertificate(): string
    {
        return $this->certificate;
    }

    public function setCertificate($certificate): self
    {
        $this->certificate = $certificate;

        return $this;
    }

    public function getCounter(): string
    {
        return $this->counter;
    }

    public function setCounter($counter): self
    {
        $this->counter = $counter;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName($name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     *  Gets the user, this U2F key belongs to.
     */
    public function getUser(): User|null
    {
        return $this->user;
    }

    /**
     * The primary key ID of this key.
     */
    public function getID(): int
    {
        return $this->id;
    }

    /**
     * Sets the user this U2F key belongs to.
     *
     * @return $this
     */
    public function setUser(User $new_user): self
    {
        $this->user = $new_user;

        return $this;
    }
}
