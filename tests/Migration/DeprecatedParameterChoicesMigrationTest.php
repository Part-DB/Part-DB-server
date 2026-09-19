<?php

declare(strict_types=1);

namespace App\Tests\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20260826000000;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

require_once dirname(__DIR__, 2).'/migrations/Version20260826000000.php';

final class DeprecatedParameterChoicesMigrationTest extends TestCase
{
    public static function platformProvider(): iterable
    {
        yield 'mysql' => [new MySQLPlatform(), 'JSON DEFAULT NULL'];
        yield 'postgresql' => [new PostgreSQLPlatform(), 'JSON DEFAULT NULL'];
        yield 'sqlite' => [new SQLitePlatform(), 'CLOB DEFAULT NULL'];
    }

    #[DataProvider('platformProvider')]
    public function testMigrationAddsPortableNullableDeprecatedChoices(
        AbstractPlatform $platform,
        string $expected_type,
    ): void {
        $sql = $this->migrationSql($platform, true);

        self::assertStringContainsString('ALTER TABLE parameter_definitions ADD deprecated_choices', $sql);
        self::assertStringContainsString($expected_type, $sql);
        self::assertStringNotContainsString('UPDATE parameters', $sql);
    }

    #[DataProvider('platformProvider')]
    public function testDownMigrationRemovesOnlyDeprecatedChoices(
        AbstractPlatform $platform,
        string $_expected_type,
    ): void {
        $sql = $this->migrationSql($platform, false);

        self::assertStringContainsString('ALTER TABLE parameter_definitions DROP', $sql);
        self::assertStringContainsString('deprecated_choices', $sql);
        self::assertStringNotContainsString('parameters SET', $sql);
    }

    /** CHOICE-DEPRECATION-012 */
    public function testSqliteMigrationPreservesExistingDefinitionAndPartValues(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE parameter_definitions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, choices CLOB DEFAULT NULL)');
        $connection->executeStatement('CREATE TABLE parameters (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, value_text VARCHAR(255) NOT NULL)');
        $connection->insert('parameter_definitions', ['choices' => json_encode(['C0G', 'X7R'], JSON_THROW_ON_ERROR)]);
        $connection->insert('parameters', ['value_text' => 'X7R']);

        $this->executeMigration($connection, true);

        $definition = $connection->fetchAssociative('SELECT choices, deprecated_choices FROM parameter_definitions WHERE id = 1');
        self::assertIsArray($definition);
        self::assertSame('["C0G","X7R"]', $definition['choices']);
        self::assertNull($definition['deprecated_choices']);
        self::assertSame('X7R', $connection->fetchOne('SELECT value_text FROM parameters WHERE id = 1'));

        $this->executeMigration($connection, false);
        self::assertFalse(
            $connection->createSchemaManager()->introspectTable('parameter_definitions')->hasColumn('deprecated_choices'),
        );
        self::assertSame('["C0G","X7R"]', $connection->fetchOne('SELECT choices FROM parameter_definitions WHERE id = 1'));
        self::assertSame('X7R', $connection->fetchOne('SELECT value_text FROM parameters WHERE id = 1'));

        $this->executeMigration($connection, true);
        self::assertTrue(
            $connection->createSchemaManager()->introspectTable('parameter_definitions')->hasColumn('deprecated_choices'),
        );
        self::assertNull($connection->fetchOne('SELECT deprecated_choices FROM parameter_definitions WHERE id = 1'));
    }

    private function migrationSql(AbstractPlatform $platform, bool $up): string
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $migration = new Version20260826000000($connection, new NullLogger());

        if ($up) {
            $migration->up(new Schema());
        } else {
            $migration->down(new Schema());
        }

        return implode("\n", array_map(
            static fn ($query): string => $query->getStatement(),
            $migration->getSql(),
        ));
    }

    private function executeMigration(Connection $connection, bool $up): void
    {
        $migration = new Version20260826000000($connection, new NullLogger());
        if ($up) {
            $migration->up(new Schema());
        } else {
            $migration->down(new Schema());
        }

        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
    }
}
