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

namespace App\Tests\Services\ImportExportSystem;

use App\Entity\Parts\Category;
use App\Services\ImportExportSystem\EntityExporter;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class EntityExporterTest extends WebTestCase
{
    /**
     * @var EntityExporter
     */
    protected $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(EntityExporter::class);
    }

    private function getEntities(): array
    {
        $entity1 = (new Category())->setName('Enitity 1')->setComment('Test');
        $entity1_1 = (new Category())->setName('Enitity 1.1')->setParent($entity1);
        $entity2 = (new Category())->setName('Enitity 2');

        return [$entity1, $entity1_1, $entity2];
    }

    public function testExportStructuralEntities(): void
    {
        $entities = $this->getEntities();

        $json_without_children = $this->service->exportEntities($entities, ['format' => 'json', 'level' => 'simple']);
        $this->assertJsonStringEqualsJsonString('[{"name":"Enitity 1","type":"category","full_name":"Enitity 1"},{"name":"Enitity 1.1","type":"category","full_name":"Enitity 1->Enitity 1.1"},{"name":"Enitity 2","type":"category","full_name":"Enitity 2"}]',
            $json_without_children);

        $json_with_children = $this->service->exportEntities($entities,
            ['format' => 'json', 'level' => 'simple', 'include_children' => true]);
        $this->assertJsonStringEqualsJsonString('[{"children":[{"children":[],"name":"Enitity 1.1","type":"category","full_name":"Enitity 1->Enitity 1.1"}],"name":"Enitity 1","type":"category","full_name":"Enitity 1"},{"children":[],"name":"Enitity 1.1","type":"category","full_name":"Enitity 1->Enitity 1.1"},{"children":[],"name":"Enitity 2","type":"category","full_name":"Enitity 2"}]',
            $json_with_children);
    }

    public function testExportEntityFromRequest(): void
    {
        $entities = $this->getEntities();

        $request = new Request();
        $request->request->set('format', 'json');
        $request->request->set('level', 'simple');
        $response = $this->service->exportEntityFromRequest($entities, $request);

        $this->assertJson($response->getContent());

        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->headers->get('Content-Disposition'));


    }
}
