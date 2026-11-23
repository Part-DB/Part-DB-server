<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Repository\Parts;

use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Parts\Part;
use App\Entity\Parts\Tag;
use App\Repository\AbstractPartsContainingRepository;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

/**
 * Unlike for the other data structures, a part can have any number of tags, so the parts of a tag are found through
 * the tag collection of the parts instead of a single field.
 * @extends AbstractPartsContainingRepository<Tag>
 */
class TagRepository extends AbstractPartsContainingRepository
{
    public function getParts(object $element, string $nameOrderDirection = "ASC"): array
    {
        if (!$element instanceof Tag) {
            throw new InvalidArgumentException('$element must be a Tag!');
        }

        return $this->createPartsWithTagsQuery([$element])
            ->orderBy('NATSORT(part.name)', $nameOrderDirection)
            ->getQuery()
            ->getResult();
    }

    public function getPartsCount(object $element): int
    {
        if (!$element instanceof Tag) {
            throw new InvalidArgumentException('$element must be a Tag!');
        }

        return $this->countPartsWithTags([$element]);
    }

    /**
     * A part which has several tags of the subtree is only counted once.
     */
    public function getPartsCountRecursive(AbstractPartsContainingDBElement $element): int
    {
        if (!$element instanceof Tag) {
            throw new InvalidArgumentException('$element must be a Tag!');
        }

        return $this->countPartsWithTags($this->getSubtree($element));
    }

    /**
     * Returns the parts which have the given tag or one of its descendants, each part only once, ordered by name.
     * @return Part[]
     */
    public function getPartsRecursive(AbstractPartsContainingDBElement $element): array
    {
        if (!$element instanceof Tag) {
            throw new InvalidArgumentException('$element must be a Tag!');
        }

        return $this->createPartsWithTagsQuery($this->getSubtree($element))
            ->orderBy('NATSORT(part.name)', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds the tag with the given name, compared case-insensitively.
     *
     * The comparison is left to the database, as its unique index decides which names are the same: MySQL for example
     * also treats names which only differ in accents as equal.
     */
    public function findOneByName(string $name): ?Tag
    {
        return $this->createQueryBuilder('tag')
            ->where('LOWER(tag.name) = :key')
            ->setParameter('key', Tag::nameKey($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Used by the UniqueEntity constraint of Tag: a tag name has to be unique across all tags (not only among the
     * siblings, like for the other data structures), and two names which only differ in case are the same tag, which
     * not every database checks for on its own (MySQL does, PostgreSQL and SQLite do not).
     * @param  array{name: string}  $criteria
     * @return Tag[]
     */
    public function findForUniquenessCheck(array $criteria): array
    {
        $tag = $this->findOneByName($criteria['name']);

        return $tag !== null ? [$tag] : [];
    }

    /**
     * Returns the names of the tags beginning with the given keyword, ordered by name.
     * @return string[]
     */
    public function findNamesStartingWith(string $keyword, int $limit): array
    {
        $rows = $this->createQueryBuilder('tag')
            ->select('tag.name')
            ->where('ILIKE(tag.name, :keyword) = TRUE')
            ->setParameter('keyword', addcslashes($keyword, '%_\\').'%')
            ->orderBy('tag.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'name');
    }

    /**
     * Returns the names of the given tag and all tags below it.
     * @return list<string>
     */
    public function getSubtreeNames(Tag $tag): array
    {
        return array_values(array_map(static fn(Tag $t): string => $t->getName(), $this->getSubtree($tag)));
    }

    /**
     * @param  Tag[]  $tags
     */
    private function countPartsWithTags(array $tags): int
    {
        if ($tags === []) {
            return 0;
        }

        return (int) $this->createPartsWithTagsQuery($tags)
            ->select('COUNT(part.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param  Tag[]  $tags
     */
    private function createPartsWithTagsQuery(array $tags): QueryBuilder
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('part')
            ->from(Part::class, 'part')
            ->where('EXISTS (SELECT _tag FROM '.Tag::class.' _tag WHERE _tag MEMBER OF part.tagEntities AND _tag IN (:tags))')
            ->setParameter('tags', $tags);
    }
}
