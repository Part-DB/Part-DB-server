<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\Base\TimestampTrait;
use App\Entity\Contracts\TimeStampableInterface;
use App\Repository\UserSystem\SavedFilterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A filter of a part list, which a user saved under a name, so that the part list can be opened again later.
 * The filter is stored as the route of the part list page and the query parameters of the applied filter form.
 * A shared filter is visible to all users, not only to its owner.
 */
#[ORM\Entity(repositoryClass: SavedFilterRepository::class)]
#[ORM\Table(name: 'saved_filters')]
#[ORM\HasLifecycleCallbacks]
class SavedFilter implements TimeStampableInterface, \Stringable
{
    use TimestampTrait;

    /**
     * The part list pages, whose filters can be saved
     */
    public const ALLOWED_ROUTES = [
        'parts_show_all',
        'parts_search',
        'part_list_category',
        'part_list_footprint',
        'part_list_manufacturer',
        'part_list_store_location',
        'part_list_supplier',
        'part_list_tags',
    ];

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    protected string $name = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_owner', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    protected ?User $owner = null;

    /**
     * @var bool If true, the filter is visible to all users and not only to its owner
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $shared = false;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\Choice(choices: self::ALLOWED_ROUTES)]
    protected string $route = 'parts_show_all';

    /**
     * @var array<string, string|int> The parameters of the route, e.g. the ID of the category
     */
    #[ORM\Column(type: Types::JSON)]
    protected array $routeParams = [];

    /**
     * @var array<string, mixed> The query parameters of the part list, which contain the filter
     */
    #[ORM\Column(name: 'query_params', type: Types::JSON)]
    protected array $query = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function isShared(): bool
    {
        return $this->shared;
    }

    public function setShared(bool $shared): self
    {
        $this->shared = $shared;
        return $this;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return array<string, string|int>
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * Sets the part list page and the filter, which this saved filter opens.
     * Only the scalar route parameters are kept, besides the locale, so that the filter opens in the language of
     * whoever opens it. Query parameters starting with an underscore are internal (e.g. of the data tables) and dropped.
     * @param  array<string, mixed>  $routeParams
     * @param  array<string, mixed>  $query
     */
    public function setTarget(string $route, array $routeParams, array $query): self
    {
        if (!in_array($route, self::ALLOWED_ROUTES, true)) {
            throw new \InvalidArgumentException(sprintf('The filters of the route "%s" can not be saved!', $route));
        }

        $this->route = $route;

        $this->routeParams = array_filter($routeParams,
            static fn ($value, $key) => is_string($key) && !str_starts_with($key, '_') && (is_string($value) || is_int($value)),
            ARRAY_FILTER_USE_BOTH);

        $this->query = array_filter($query,
            static fn ($key) => is_string($key) && !str_starts_with($key, '_'),
            ARRAY_FILTER_USE_KEY);

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
