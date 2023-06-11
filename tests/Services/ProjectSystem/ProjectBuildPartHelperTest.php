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

namespace App\Tests\Services\ProjectSystem;

use App\Entity\ProjectSystem\Project;
use App\Services\ProjectSystem\ProjectBuildPartHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProjectBuildPartHelperTest extends WebTestCase
{
    /** @var ProjectBuildPartHelper */
    protected $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(ProjectBuildPartHelper::class);
    }

    public function testGetPartInitialization(): void
    {
        $project = new Project();
        $project->setName('Project 1');
        $project->setDescription('Description 1');

        $part = $this->service->getPartInitialization($project);
        $this->assertSame('Project 1', $part->getName());
        $this->assertSame('Description 1', $part->getDescription());
        $this->assertSame($project, $part->getBuiltProject());
        $this->assertSame($part, $project->getBuildPart());
    }
}
