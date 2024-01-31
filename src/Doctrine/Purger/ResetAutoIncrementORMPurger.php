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

declare(strict_types=1);

namespace App\Doctrine\Purger;

use Doctrine\Common\DataFixtures\Purger\ORMPurgerInterface;
use Doctrine\Common\DataFixtures\Purger\PurgerInterface;
use Doctrine\Common\DataFixtures\Sorter\TopologicalSorter;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

use Doctrine\ORM\Mapping\ClassMetadataInfo;

use function array_reverse;
use function assert;
use function count;
use function is_callable;
use function method_exists;
use function preg_match;

/**
 * Class responsible for purging databases of data before reloading data fixtures.
 *
 * Based on Doctrine\Common\DataFixtures\Purger\ORMPurger
 */
class ResetAutoIncrementORMPurger implements PurgerInterface, ORMPurgerInterface
{
    final public const PURGE_MODE_DELETE   = 1;
    final public const PURGE_MODE_TRUNCATE = 2;

    /**
     * If the purge should be done through DELETE or TRUNCATE statements
     *
     * @var int
     */
    private int $purgeMode = self::PURGE_MODE_DELETE;

    /**
     * Construct new purger instance.
     *
     * @param  EntityManagerInterface|null  $em  EntityManagerInterface instance used for persistence.
     * @param  string[]  $excluded  array of table/view names to be excluded from purge
     */
    public function __construct(
        private ?EntityManagerInterface $em = null,
        /**
         * Table/view names to be excluded from purge
         */
        private readonly array $excluded = []
    )
    {
    }

    /**
     * Set the purge mode
     *
     *
     */
    public function setPurgeMode(int $mode): void
    {
        $this->purgeMode = $mode;
    }

    /**
     * Get the purge mode
     */
    public function getPurgeMode(): int
    {
        return $this->purgeMode;
    }

    /** @inheritDoc */
    public function setEntityManager(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    /**
     * Retrieve the EntityManagerInterface instance this purger instance is using.
     *
     * @return EntityManagerInterface
     */
    public function getObjectManager(): ?EntityManagerInterface
    {
        return $this->em;
    }

    /** @inheritDoc */
    public function purge(): void
    {
        $classes = [];

        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $metadata) {
            if ($metadata->isMappedSuperclass || ($metadata->isEmbeddedClass !== null && $metadata->isEmbeddedClass)) {
                continue;
            }

            $classes[] = $metadata;
        }

        $commitOrder = $this->getCommitOrder($this->em, $classes);

        // Get platform parameters
        $platform = $this->em->getConnection()->getDatabasePlatform();

        // Drop association tables first
        $orderedTables = $this->getAssociationTables($commitOrder, $platform);

        // Drop tables in reverse commit order
        for ($i = count($commitOrder) - 1; $i >= 0; --$i) {
            $class = $commitOrder[$i];

            if (
                ($class->isEmbeddedClass !== null && $class->isEmbeddedClass) ||
                $class->isMappedSuperclass ||
                ($class->isInheritanceTypeSingleTable() && $class->name !== $class->rootEntityName)
            ) {
                continue;
            }

            $orderedTables[] = $this->getTableName($class, $platform);
        }

        $connection            = $this->em->getConnection();
        $filterExpr            = method_exists(
            $connection->getConfiguration(),
            'getFilterSchemaAssetsExpression'
        ) ? $connection->getConfiguration()->getFilterSchemaAssetsExpression() : null;
        $emptyFilterExpression = empty($filterExpr);

        $schemaAssetsFilter = method_exists(
            $connection->getConfiguration(),
            'getSchemaAssetsFilter'
        ) ? $connection->getConfiguration()->getSchemaAssetsFilter() : null;

        //Disable foreign key checks
        if($platform instanceof AbstractMySQLPlatform) {
            $connection->executeQuery('SET foreign_key_checks = 0;');
        }

        foreach ($orderedTables as $tbl) {
            // If we have a filter expression, check it and skip if necessary
            if (! $emptyFilterExpression && ! preg_match($filterExpr, (string) $tbl)) {
                continue;
            }

            // The table name might be quoted, we have to trim it
            // See https://github.com/Part-DB/Part-DB-server/issues/299
            $tbl = trim((string) $tbl, '"');
            $tbl = trim($tbl, '`');

            // If the table is excluded, skip it as well
            if (in_array($tbl, $this->excluded, true)) {
                continue;
            }

            // Support schema asset filters as presented in
            if (is_callable($schemaAssetsFilter) && ! $schemaAssetsFilter($tbl)) {
                continue;
            }

            if ($this->purgeMode === self::PURGE_MODE_DELETE) {
                $connection->executeStatement($this->getDeleteFromTableSQL($tbl, $platform));
            } else {
                $connection->executeStatement($platform->getTruncateTableSQL($tbl, true));
            }

            //Reseting autoincrement is only supported on MySQL platforms
            if ($platform instanceof AbstractMySQLPlatform ) { //|| $platform instanceof SqlitePlatform) {
                $connection->executeQuery($this->getResetAutoIncrementSQL($tbl, $platform));
            }
        }

        //Reenable foreign key checks
        if($platform instanceof AbstractMySQLPlatform) {
            $connection->executeQuery('SET foreign_key_checks = 1;');
        }
    }

    private function getResetAutoIncrementSQL(string $tableName, AbstractPlatform $platform): string
    {
        $tableIdentifier = new Identifier($tableName);

        if ($platform instanceof AbstractMySQLPlatform) {
            return 'ALTER TABLE '.$tableIdentifier->getQuotedName($platform).' AUTO_INCREMENT = 1;';
        }

        //This seems to cause problems somehow
        /*if ($platform instanceof SqlitePlatform) {
            return 'DELETE FROM `sqlite_sequence` WHERE name = \''.$tableIdentifier->getQuotedName($platform).'\';';
        }*/
    }

    /**
     * @param ClassMetadata[] $classes
     *
     * @return ClassMetadata[]
     */
    private function getCommitOrder(EntityManagerInterface $em, array $classes): array
    {
        $sorter = new TopologicalSorter();

        foreach ($classes as $class) {
            if (! $sorter->hasNode($class->name)) {
                $sorter->addNode($class->name, $class);
            }

            // $class before its parents
            foreach ($class->parentClasses as $parentClass) {
                $parentClass     = $em->getClassMetadata($parentClass);
                $parentClassName = $parentClass->getName();

                if (! $sorter->hasNode($parentClassName)) {
                    $sorter->addNode($parentClassName, $parentClass);
                }

                $sorter->addDependency($class->name, $parentClassName);
            }

            foreach ($class->associationMappings as $assoc) {
                if (! $assoc['isOwningSide']) {
                    continue;
                }

                $targetClass = $em->getClassMetadata($assoc['targetEntity']);
                assert($targetClass instanceof ClassMetadata);
                $targetClassName = $targetClass->getName();

                if (! $sorter->hasNode($targetClassName)) {
                    $sorter->addNode($targetClassName, $targetClass);
                }

                // add dependency ($targetClass before $class)
                $sorter->addDependency($targetClassName, $class->name);

                // parents of $targetClass before $class, too
                foreach ($targetClass->parentClasses as $parentClass) {
                    $parentClass     = $em->getClassMetadata($parentClass);
                    $parentClassName = $parentClass->getName();

                    if (! $sorter->hasNode($parentClassName)) {
                        $sorter->addNode($parentClassName, $parentClass);
                    }

                    $sorter->addDependency($parentClassName, $class->name);
                }
            }
        }

        return array_reverse($sorter->sort());
    }

    private function getAssociationTables(array $classes, AbstractPlatform $platform): array
    {
        $associationTables = [];

        foreach ($classes as $class) {
            foreach ($class->associationMappings as $assoc) {
                if (! $assoc['isOwningSide'] || $assoc['type'] !== ClassMetadataInfo::MANY_TO_MANY) {
                    continue;
                }

                $associationTables[] = $this->getJoinTableName($assoc, $class, $platform);
            }
        }

        return $associationTables;
    }

    private function getTableName(ClassMetadata $class, AbstractPlatform $platform): string
    {
        if (isset($class->table['schema']) && ! method_exists($class, 'getSchemaName')) {
            return $class->table['schema'] . '.' .
                $this->em->getConfiguration()
                    ->getQuoteStrategy()
                    ->getTableName($class, $platform);
        }

        return $this->em->getConfiguration()->getQuoteStrategy()->getTableName($class, $platform);
    }

    private function getJoinTableName(
        array $assoc,
        ClassMetadata $class,
        AbstractPlatform $platform
    ): string {
        if (isset($assoc['joinTable']['schema']) && ! method_exists($class, 'getSchemaName')) {
            return $assoc['joinTable']['schema'] . '.' .
                $this->em->getConfiguration()
                    ->getQuoteStrategy()
                    ->getJoinTableName($assoc, $class, $platform);
        }

        return $this->em->getConfiguration()->getQuoteStrategy()->getJoinTableName($assoc, $class, $platform);
    }

    private function getDeleteFromTableSQL(string $tableName, AbstractPlatform $platform): string
    {
        $tableIdentifier = new Identifier($tableName);

        return 'DELETE FROM ' . $tableIdentifier->getQuotedName($platform);
    }
}
