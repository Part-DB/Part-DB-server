<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Entity\UserSystem;

use App\Entity\Base\TimestampTrait;
use Doctrine\ORM\Mapping as ORM;
use Jbtronics\TFAWebauthn\Model\LegacyU2FKeyInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="u2f_keys",
 * uniqueConstraints={
 * @ORM\UniqueConstraint(name="user_unique",columns={"user_id",
 * "key_handle"})
 * })
 * @ORM\HasLifecycleCallbacks()
 */
class U2FKey implements LegacyU2FKeyInterface
{
    use TimestampTrait;

    /**
     * We have to restrict the length here, as InnoDB only supports key index with max. 767 Bytes.
     * Max length of keyhandles should be 128. (According to U2F_MAX_KH_SIZE in FIDO example C code).
     *
     * @ORM\Column(type="string", length=128)
     *
     * @var string
     **/
    public string $keyHandle;

    /**
     * @ORM\Column(type="string")
     *
     * @var string
     **/
    public string $publicKey;

    /**
     * @ORM\Column(type="text")
     *
     * @var string
     **/
    public string $certificate;

    /**
     * @ORM\Column(type="string")
     *
     * @var int
     **/
    public int $counter;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected int $id;

    /**
     * @ORM\Column(type="string")
     *
     * @var string
     **/
    protected string $name;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\UserSystem\User", inversedBy="u2fKeys")
     **/
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

    public function getCounter(): int
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
     * Gets the user, this U2F key belongs to.
     */
    public function getUser(): User
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
