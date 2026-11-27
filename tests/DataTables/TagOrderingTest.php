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

namespace App\Tests\DataTables;

use App\DataTables\PartsDataTable;
use App\Entity\Parts\Part;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Sorting the part table by the tags must not change the results of the other filters, e.g. the number of lots, which
 * are counted over joined rows.
 */
final class TagOrderingTest extends WebTestCase
{
    public function testSortingByTagsKeepsTheLotCountFilter(): void
    {
        $client = static::createClient([], ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'test']);
        $client->catchExceptions(false);

        //Part 3 of the fixtures has two lots, give it two tags as well
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->find(Part::class, 3)->setTags('Zeta, Alpha');
        $em->flush();

        $table = self::getContainer()->get(DataTableFactory::class)->createFromType(PartsDataTable::class);
        $tagsColumn = $table->getColumnByName('tags')->getIndex();
        $nameColumn = $table->getColumnByName('name')->getIndex();

        $byName = $this->requestPartsWithTwoLots($client, $nameColumn);
        $byTags = $this->requestPartsWithTwoLots($client, $tagsColumn);

        $this->assertGreaterThanOrEqual(1, $byName['recordsFiltered']);
        $this->assertSame($byName['recordsFiltered'], $byTags['recordsFiltered']);
        $this->assertCount(count($byName['data']), $byTags['data']);
    }

    public function testPartsWithoutTagsAreSortedLast(): void
    {
        $client = static::createClient([], ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'test']);
        $client->catchExceptions(false);

        $table = self::getContainer()->get(DataTableFactory::class)->createFromType(PartsDataTable::class);
        $tagsColumn = $table->getColumnByName('tags')->getIndex();

        foreach (['asc', 'desc'] as $direction) {
            $client->request('POST', '/en/parts', ['_dt' => 'dt', 'order' => [['column' => $tagsColumn, 'dir' => $direction]]]);
            $this->assertResponseIsSuccessful();
            $rows = json_decode((string) $client->getResponse()->getContent(), true)['data'];

            //Only part 2 has tags in the fixtures
            $this->assertStringContainsString('Part2', (string) $rows[0]['tags'], $direction);
            $this->assertSame('', trim(strip_tags((string) end($rows)['tags'])), $direction);
        }
    }

    /**
     * @return array{recordsFiltered: int, data: list<array<string, mixed>>}
     */
    private function requestPartsWithTwoLots(KernelBrowser $client, int $orderColumn): array
    {
        $filter = http_build_query(['part_filter' => [
            'lotCount' => ['operator' => '=', 'value1' => 2],
            'submit' => '',
        ]]);
        $client->request('POST', '/en/parts?'.$filter, ['_dt' => 'dt', 'order' => [['column' => $orderColumn, 'dir' => 'asc']]]);
        $this->assertResponseIsSuccessful();

        return json_decode((string) $client->getResponse()->getContent(), true);
    }
}
