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

declare(strict_types=1);


namespace App\Entity\Parts;

use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embeddable;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * This class represents a reference to a info provider inside a part.
 * @see \App\Tests\Entity\Parts\InfoProviderReferenceTest
 */
#[Embeddable]
class InfoProviderReference
{

    /** @var string|null The key referencing the provider used to get this part, or null if it was not provided by a data provider */
    #[Column(type: Types::STRING, nullable: true)]
    #[Groups(['provider_reference:read'])]
    private ?string $provider_key = null;

    /** @var string|null The id of this part inside the provider system or null if the part was not provided by a data provider */
    #[Column(type: Types::STRING, nullable: true)]
    #[Groups(['provider_reference:read'])]
    private ?string $provider_id = null;

    /**
     * @var string|null The url of this part inside the provider system or null if this info is not existing
     */
    #[Column(type: Types::STRING, nullable: true)]
    #[Groups(['provider_reference:read'])]
    private ?string $provider_url = null;

    #[Column(type: Types::DATETIME_MUTABLE, nullable: true,  options: ['default' => null])]
    #[Groups(['provider_reference:read'])]
    private ?\DateTime $last_updated = null;

    /**
     * Constructing is forbidden from outside.
     */
    private function __construct()
    {

    }

    /**
     * Returns the key usable to identify the provider, which provided this part. Returns null, if the part was not created by a provider.
     * @return string|null
     */
    public function getProviderKey(): ?string
    {
        return $this->provider_key;
    }

    /**
     * Returns the id of this part inside the provider system or null if the part was not provided by a data provider.
     * @return string|null
     */
    public function getProviderId(): ?string
    {
        return $this->provider_id;
    }

    /**
     * Returns the url of this part inside the provider system or null if this info is not existing.
     * @return string|null
     */
    public function getProviderUrl(): ?string
    {
        return $this->provider_url;
    }

    /**
     * Gets the time, when the part was last time updated by the provider.
     * @return \DateTimeInterface|null
     */
    public function getLastUpdated(): ?\DateTimeInterface
    {
        return $this->last_updated;
    }

    /**
     * Returns true, if this part was created based on infos from a provider.
     * Or false, if this part was created by a user manually.
     * @return bool
     */
    public function isProviderCreated(): bool
    {
        return $this->provider_key !== null;
    }

    /**
     * Creates a new instance, without any provider information.
     * Use this for parts, which are created by a user manually.
     * @return InfoProviderReference
     */
    public static function noProvider(): self
    {
        $ref = new InfoProviderReference();
        $ref->provider_key = null;
        $ref->provider_id = null;
        $ref->provider_url = null;
        $ref->last_updated = null;
        return $ref;
    }

    /**
     * Creates a reference to an info provider based on the given parameters.
     * @param  string  $provider_key
     * @param  string  $provider_id
     * @param  string|null  $provider_url
     * @return self
     */
    public static function providerReference(string $provider_key, string $provider_id, ?string $provider_url = null): self
    {
        $ref = new InfoProviderReference();
        $ref->provider_key = $provider_key;
        $ref->provider_id = $provider_id;
        $ref->provider_url = $provider_url;
        $ref->last_updated = new \DateTime();
        return $ref;
    }

    /**
     * Creates a reference to an info provider based on the given Part DTO
     * @param  SearchResultDTO  $dto
     * @return self
     */
    public static function fromPartDTO(SearchResultDTO $dto): self
    {
        $ref = new InfoProviderReference();
        $ref->provider_key = $dto->provider_key;
        $ref->provider_id = $dto->provider_id;
        $ref->provider_url = $dto->provider_url;
        $ref->last_updated = new \DateTime();
        return $ref;
    }
}