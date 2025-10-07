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

use PHPUnit\Framework\Attributes\DataProvider;
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
    private ?SandboxedTwigFactory $service = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(SandboxedTwigFactory::class);
    }

    public static function twigDataProvider(): \Iterator
    {
        yield [' {% for i in range(1, 3) %}
                    {{ part.id }}
                    {{ part.name }}
                    {{ part.lastModified | format_datetime }}
               {% endfor %}
            '];
        yield [' {% if part.category %}
                   {{ part.category }}
               {% endif %}
            '];
        yield [' {% set a = random(1, 3) %}
               {{ 1 + 2 | abs }}
               {{ "test" | capitalize | escape | lower | raw }}
               {{ "\n"  | nl2br | trim | title | url_encode | reverse }}
            '];
        yield ['
                {{ location.isRoot}} {{ location.isChildOf(location) }} {{ location.comment }} {{ location.level }}
                {{ location.fullPath }} {% set arr =  location.pathArray %} {% set child = location.children %} {{location.notSelectable}}
            '];
        yield ['
                {{ part.needsReview }} {{ part.tags }} {{ part.mass }}
            '];
        yield ['
                {{ entity_type(part) is object }}
            '];
        yield ['
                {% apply placeholders(part) %}[[NAME]]{% endapply %}</br>
                {{ placeholder("[[NAME]]", part) }}
            '];
    }

    public static function twigNotAllowedDataProvider(): \Iterator
    {
        yield ['{% block test %} {% endblock %}'];
        yield ['{% deprecated test %}'];
        yield ['{% flush %}'];
        yield ["{{ part.setName('test') }}"];
        yield ['{{ part.setCategory(null) }}'];
    }

    #[DataProvider('twigDataProvider')]
    public function testTwigFeatures(string $twig): void
    {
        $options = new LabelOptions();
        $options->setSupportedElement(LabelSupportedElement::PART);
        $options->setLines($twig);
        $options->setProcessMode(LabelProcessMode::TWIG);

        $twig = $this->service->createTwig($options);
        $str = $twig->render('lines', [
            'part' => new Part(),
            'lot' => new PartLot(),
            'location' => new StorageLocation(),
        ]);

        $this->assertIsString($str);
    }

    #[DataProvider('twigNotAllowedDataProvider')]
    public function testTwigForbidden(string $twig): void
    {
        $this->expectException(SecurityError::class);

        $options = new LabelOptions();
        $options->setSupportedElement(LabelSupportedElement::PART);
        $options->setLines($twig);
        $options->setProcessMode(LabelProcessMode::TWIG);

        $twig = $this->service->createTwig($options);
        $str = $twig->render('lines', [
            'part' => new Part(),
            'lot' => new PartLot(),
            'location' => new StorageLocation(),
        ]);

        $this->assertIsString($str);
    }
}
