<?php

declare(strict_types=1);

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
namespace App\Tests\Entity\Parts;

use App\Entity\Parts\PartCustomState;
use App\Entity\Parts\PartCustomStateColor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PartCustomStateColorTest extends TestCase
{
    public static function colorProvider(): array
    {
        return [
            [PartCustomStateColor::PRIMARY, 'text-bg-primary'],
            [PartCustomStateColor::SECONDARY, 'text-bg-secondary'],
            [PartCustomStateColor::INFO, 'text-bg-info'],
            [PartCustomStateColor::SUCCESS, 'text-bg-success'],
            [PartCustomStateColor::WARNING, 'text-bg-warning'],
            [PartCustomStateColor::DANGER, 'text-bg-danger'],
            [PartCustomStateColor::LIGHT, 'text-bg-light'],
            [PartCustomStateColor::DARK, 'text-bg-dark'],
        ];
    }

    #[DataProvider('colorProvider')]
    public function testToBadgeClassMapsEveryColorToAFixedClass(PartCustomStateColor $color, string $expectedClass): void
    {
        $this->assertSame($expectedClass, $color->toBadgeClass());
    }

    public function testUnknownColorValueIsRejected(): void
    {
        $this->assertNull(PartCustomStateColor::tryFrom('not-a-real-color'));
    }

    public function testPartCustomStateDefaultsToNoColor(): void
    {
        $state = new PartCustomState();

        $this->assertNull($state->getColor());
    }

    public function testPartCustomStateColorCanBeSetAndRetrieved(): void
    {
        $state = new PartCustomState();
        $state->setColor(PartCustomStateColor::WARNING);

        $this->assertSame(PartCustomStateColor::WARNING, $state->getColor());

        $state->setColor(null);
        $this->assertNull($state->getColor());
    }

    public function testBadgeClassFallsBackToThePreviousAppearance(): void
    {
        //A state without a configured color has to keep looking exactly the way it did before colors existed
        $this->assertSame('bg-primary', (new PartCustomState())->getBadgeClass());
    }

    #[DataProvider('colorProvider')]
    public function testBadgeClassUsesTheConfiguredColor(PartCustomStateColor $color, string $expectedClass): void
    {
        $state = (new PartCustomState())->setColor($color);

        $this->assertSame($expectedClass, $state->getBadgeClass());
    }
}
