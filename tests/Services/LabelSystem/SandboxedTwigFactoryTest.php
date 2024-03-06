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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Services\LabelSystem;

use App\Entity\LabelSystem\LabelOptions;
use App\Entity\LabelSystem\LabelProcessMode;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Services\LabelSystem\SandboxedTwigFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Twig\Sandbox\SecurityError;

class SandboxedTwigFactoryTest extends WebTestCase
{
    private ?object $service = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(SandboxedTwigFactory::class);
    }

    public function twigDataProvider(): array
    {
        return [
            [' {% for i in range(1, 3) %}
                    {{ part.id }}
                    {{ part.name }}
                    {{ part.lastModified | format_datetime }}
               {% endfor %}
            '],
            [' {% if part.category %}
                   {{ part.category }}
               {% endif %}
            '],
            [' {% set a = random(1, 3) %}
               {{ 1 + 2 | abs }}
               {{ "test" | capitalize | escape | lower | raw }}
               {{ "\n"  | nl2br | trim | title | url_encode | reverse }}
            '],
            ['
                {{ location.isRoot}} {{ location.isChildOf(location) }} {{ location.comment }} {{ location.level }}
                {{ location.fullPath }} {% set arr =  location.pathArray %} {% set child = location.children %} {{location.childrenNotSelectable}}
            '],
            ['
                {{ part.reviewNeeded }} {{ part.tags }} {{ part.mass }}
            '],
        ];
    }

    public function twigNotAllowedDataProvider(): array
    {
        return [
            ['{% block test %} {% endblock %}'],
            ['{% deprecated test %}'],
            ['{% flush %}'],
            ["{{ part.setName('test') }}"],
            ['{{ part.setCategory(null) }}'],
        ];
    }

    /**
     * @dataProvider twigDataProvider
     */
    public function testTwigFeatures(string $twig): void
    {
        $options = new LabelOptions();
        $options->setSupportedElement(LabelSupportedElement::PART);
        $options->setLines($twig);
        $options->setProcessMode(LabelProcessMode::TWIG);

        $twig = $this->service->getTwig($options);
        $str = $twig->render('lines', [
            'part' => new Part(),
            'lot' => new PartLot(),
            'location' => new StorageLocation(),
        ]);

        $this->assertIsString($str);
    }

    /**
     * @dataProvider twigNotAllowedDataProvider
     */
    public function testTwigForbidden(string $twig): void
    {
        $this->expectException(SecurityError::class);

        $options = new LabelOptions();
        $options->setSupportedElement(LabelSupportedElement::PART);
        $options->setLines($twig);
        $options->setProcessMode(LabelProcessMode::TWIG);

        $twig = $this->service->getTwig($options);
        $str = $twig->render('lines', [
            'part' => new Part(),
            'lot' => new PartLot(),
            'location' => new StorageLocation(),
        ]);

        $this->assertIsString($str);
    }
}
