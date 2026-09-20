<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('DB')]
#[Group('slow')]
final class ParameterDefinitionSchemaTest extends KernelTestCase
{
    public function testParameterDefinitionSchema(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        $schema_manager = $connection->createSchemaManager();

        self::assertTrue($schema_manager->tablesExist(['parameter_definitions']));
        $definition_table = $schema_manager->introspectTable('parameter_definitions');
        self::assertTrue($definition_table->hasColumn('normalized_name'));
        self::assertTrue($definition_table->hasColumn('input_type'));
        self::assertTrue($definition_table->hasColumn('choices'));
        self::assertTrue($definition_table->hasColumn('deprecated_choices'));
        self::assertTrue($definition_table->hasIndex('parameter_definition_normalized_name_unique'));
        self::assertTrue($definition_table->getIndex('parameter_definition_normalized_name_unique')->isUnique());

        $parameter_table = $schema_manager->introspectTable('parameters');
        self::assertFalse($parameter_table->hasColumn('input_type'));
        self::assertFalse($parameter_table->hasColumn('choices'));
        self::assertTrue($parameter_table->hasColumn('definition_id'));
        self::assertTrue($parameter_table->getColumn('definition_id')->getNotnull() === false);
        self::assertTrue($parameter_table->hasIndex('parameter_definition_value_idx'));
        self::assertSame(
            ['definition_id', 'value_text', 'type', 'element_id'],
            $parameter_table->getIndex('parameter_definition_value_idx')->getColumns(),
        );

        $foreign_keys = array_values(array_filter(
            $parameter_table->getForeignKeys(),
            static fn ($foreign_key): bool => ['definition_id'] === $foreign_key->getLocalColumns(),
        ));
        self::assertCount(1, $foreign_keys);
        self::assertSame('parameter_definitions', $foreign_keys[0]->getForeignTableName());
    }
}
