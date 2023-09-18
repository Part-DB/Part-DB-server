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


namespace App\Entity\UserSystem;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\TimestampTrait;
use App\Repository\UserSystem\ApiTokenRepository;
use App\State\CurrentApiTokenProvider;
use App\State\PartDBInfoProvider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints\NotBlank;

#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
#[ORM\Table(name: 'api_tokens')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['name', 'user'])]

#[ApiResource(
    uriTemplate: '/tokens/current.{_format}',
    description: 'A token used to authenticate API requests.',
    operations: [new Get(openapiContext: ['summary' => 'Get information about the API token that is currently used.'])],
    normalizationContext: ['groups' => ['token:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    provider: CurrentApiTokenProvider::class,
)]
#[ApiFilter(PropertyFilter::class)]
class ApiToken
{

    use TimestampTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected int $id;

    #[ORM\Column(type: Types::STRING)]
    #[NotBlank]
    #[Groups('token:read')]
    protected string $name = '';

    #[ORM\ManyToOne(inversedBy: 'api_tokens')]
    #[Groups('token:read')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups('token:read')]
    private ?\DateTimeInterface $valid_until;

    #[ORM\Column(length: 68, unique: true)]
    private string $token;

    #[ORM\Column(type: Types::SMALLINT, enumType: ApiTokenLevel::class)]
    #[Groups('token:read')]
    private ApiTokenLevel $level = ApiTokenLevel::READ_ONLY;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups('token:read')]
    private ?\DateTimeInterface $last_time_used = null;

    public function __construct(ApiTokenType $tokenType = ApiTokenType::PERSONAL_ACCESS_TOKEN)
    {
        // Generate a rondom token on creation. The tokenType is 3 characters long (plus underscore), so the token is 68 characters long.
        $this->token = $tokenType->getTokenPrefix() . bin2hex(random_bytes(32));

        //By default, tokens are valid for 1 year.
        $this->valid_until = new \DateTime('+1 year');
    }

    public function getTokenType(): ApiTokenType
    {
        return ApiTokenType::getTypeFromToken($this->token);
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): ApiToken
    {
        $this->user = $user;
        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->valid_until;
    }

    /**
     * Checks if the token is still valid.
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->valid_until === null || $this->valid_until > new \DateTime();
    }

    public function setValidUntil(?\DateTimeInterface $valid_until): ApiToken
    {
        $this->valid_until = $valid_until;
        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): ApiToken
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Gets the last time the token was used to authenticate or null if it was never used.
     * @return \DateTimeInterface|null
     */
    public function getLastTimeUsed(): ?\DateTimeInterface
    {
        return $this->last_time_used;
    }

    /**
     * Sets the last time the token was used to authenticate.
     * @param \DateTimeInterface|null $last_time_used
     * @return ApiToken
     */
    public function setLastTimeUsed(?\DateTimeInterface $last_time_used): ApiToken
    {
        $this->last_time_used = $last_time_used;
        return $this;
    }

    public function getLevel(): ApiTokenLevel
    {
        return $this->level;
    }

    public function setLevel(ApiTokenLevel $level): ApiToken
    {
        $this->level = $level;
        return $this;
    }


}