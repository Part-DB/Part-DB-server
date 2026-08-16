<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace App\Entity\Parameters;

use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\ApiPlatform\Filter\LikeFilter;
use App\ApiPlatform\ParameterDefinitionDeleteProcessor;
use App\Entity\Base\AbstractNamedDBElement;
use App\EntityListeners\TreeCacheInvalidationListener;
use App\Repository\ParameterDefinitionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use LogicException;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ParameterDefinitionRepository::class)]
#[ORM\Table(name: 'parameter_definitions')]
#[ORM\UniqueConstraint(name: 'parameter_definition_normalized_name_unique', columns: ['normalized_name'])]
#[ORM\Index(columns: ['name'], name: 'parameter_definition_name_idx')]
#[ORM\HasLifecycleCallbacks]
#[ORM\EntityListeners([TreeCacheInvalidationListener::class])]
#[UniqueEntity(fields: ['normalized_name'], errorPath: 'name', message: 'parameter_definition.name_unique')]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@parameter_definitions.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)', processor: ParameterDefinitionDeleteProcessor::class),
    ],
    normalizationContext: ['groups' => ['parameter_definition:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['parameter_definition:write', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ['name', 'symbol', 'unit'])]
#[ApiFilter(DateFilter::class, strategy: DateFilterInterface::EXCLUDE_NULL)]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
class ParameterDefinition extends AbstractNamedDBElement
{
    public const INPUT_TYPE_TEXT = 'text';
    public const INPUT_TYPE_CHOICE = 'choice';
    public const MAX_CHOICE_LENGTH = 255;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $normalized_name = '';

    #[ORM\Column(type: Types::STRING, length: 16, options: ['default' => self::INPUT_TYPE_TEXT])]
    #[Assert\Choice(choices: [self::INPUT_TYPE_TEXT, self::INPUT_TYPE_CHOICE])]
    #[Groups(['full', 'import', 'parameter_definition:read', 'parameter_definition:write'])]
    private string $input_type = self::INPUT_TYPE_TEXT;

    /** @var list<string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['full', 'import', 'parameter_definition:read', 'parameter_definition:write'])]
    private ?array $choices = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\Length(max: 20)]
    #[Groups(['full', 'import', 'parameter_definition:read', 'parameter_definition:write'])]
    private string $symbol = '';

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Length(max: 50)]
    #[Groups(['full', 'import', 'parameter_definition:read', 'parameter_definition:write'])]
    private string $unit = '';

    /** @var Collection<int, AbstractParameter> */
    #[ORM\OneToMany(mappedBy: 'definition', targetEntity: AbstractParameter::class)]
    private Collection $parameter_usages;

    public function __construct()
    {
        $this->parameter_usages = new ArrayCollection();
    }

    public function setName(string $new_name): self
    {
        $new_name = trim($new_name);
        parent::setName($new_name);
        $this->normalized_name = self::normalize($new_name);

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateNormalizedName(): void
    {
        $this->normalized_name = self::normalize($this->name);
    }

    public function getNormalizedName(): string
    {
        return $this->normalized_name;
    }

    public function getInputType(): string
    {
        return $this->input_type;
    }

    public function setInputType(string $input_type): self
    {
        if (!in_array($input_type, [self::INPUT_TYPE_TEXT, self::INPUT_TYPE_CHOICE], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported parameter input type "%s".', $input_type));
        }

        $this->input_type = $input_type;
        if (self::INPUT_TYPE_TEXT === $input_type) {
            $this->choices = null;
        }

        return $this;
    }

    /** @return list<string> */
    public function getChoices(): array
    {
        return $this->choices ?? [];
    }

    /** @param list<string>|null $choices */
    public function setChoices(?array $choices): self
    {
        $canonical_choices = self::canonicalizeChoices($choices ?? []);
        $this->choices = [] === $canonical_choices ? null : $canonical_choices;

        return $this;
    }

    public function getChoicesText(): string
    {
        return implode("\n", $this->getChoices());
    }

    public function setChoicesText(?string $choices_text): self
    {
        if (null === $choices_text || '' === trim($choices_text)) {
            return $this->setChoices(null);
        }

        return $this->setChoices(preg_split('/\R/', $choices_text) ?: []);
    }

    public function addChoice(string $choice): string
    {
        if (self::INPUT_TYPE_CHOICE !== $this->input_type) {
            throw new LogicException('Choices can only be added to a choice parameter definition.');
        }

        $choice = trim($choice);
        if ('' === $choice) {
            throw new InvalidArgumentException('A parameter choice must not be empty.');
        }
        if (mb_strlen($choice) > self::MAX_CHOICE_LENGTH) {
            throw new InvalidArgumentException(sprintf('A parameter choice must not exceed %d characters.', self::MAX_CHOICE_LENGTH));
        }

        $canonical_choice = $this->findCanonicalChoice($choice);
        if (null !== $canonical_choice) {
            return $canonical_choice;
        }

        $choices = $this->getChoices();
        $choices[] = $choice;
        $this->choices = $choices;

        return $choice;
    }

    public function findCanonicalChoice(string $choice): ?string
    {
        $normalized_choice = self::normalize($choice);
        foreach ($this->getChoices() as $canonical_choice) {
            if (self::normalize($canonical_choice) === $normalized_choice) {
                return $canonical_choice;
            }
        }

        return null;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): self
    {
        $this->symbol = $symbol;

        return $this;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    /** @return Collection<int, AbstractParameter> */
    public function getParameterUsages(): Collection
    {
        return $this->parameter_usages;
    }

    public function addParameterUsage(AbstractParameter $parameter): self
    {
        if (!$this->parameter_usages->contains($parameter)) {
            $this->parameter_usages->add($parameter);
        }

        if ($parameter->getDefinition() !== $this) {
            $parameter->setDefinition($this);
        }

        return $this;
    }

    public function removeParameterUsage(AbstractParameter $parameter): self
    {
        $this->parameter_usages->removeElement($parameter);

        if ($parameter->getDefinition() === $this) {
            $parameter->setDefinition(null);
        }

        return $this;
    }

    #[Assert\Callback]
    public function validateChoices(ExecutionContextInterface $context): void
    {
        if (self::INPUT_TYPE_TEXT === $this->input_type && [] !== $this->getChoices()) {
            $context->buildViolation('A text parameter definition must not contain choices.')
                ->atPath('choices')
                ->addViolation();
        }

        foreach ($this->getChoices() as $choice) {
            if (mb_strlen($choice) > self::MAX_CHOICE_LENGTH) {
                $context->buildViolation(sprintf('A parameter choice must not exceed %d characters.', self::MAX_CHOICE_LENGTH))
                    ->atPath('choices')
                    ->addViolation();
            }
        }
    }

    /**
     * @param list<string> $choices
     * @return list<string>
     */
    private static function canonicalizeChoices(array $choices): array
    {
        $canonical_choices = [];
        $seen_choices = [];

        foreach ($choices as $choice) {
            if (!is_string($choice)) {
                throw new InvalidArgumentException('Parameter choices must be strings.');
            }

            $choice = trim($choice);
            if ('' === $choice) {
                continue;
            }
            if (mb_strlen($choice) > self::MAX_CHOICE_LENGTH) {
                throw new InvalidArgumentException(sprintf('A parameter choice must not exceed %d characters.', self::MAX_CHOICE_LENGTH));
            }

            $normalized_choice = self::normalize($choice);
            if (isset($seen_choices[$normalized_choice])) {
                continue;
            }

            $seen_choices[$normalized_choice] = true;
            $canonical_choices[] = $choice;
        }

        return $canonical_choices;
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
