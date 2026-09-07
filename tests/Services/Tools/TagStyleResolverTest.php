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
namespace App\Tests\Services\Tools;

use App\Services\Tools\TagStyleResolver;
use App\Settings\TagStyleSettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\TestCase;

final class TagStyleResolverTest extends TestCase
{
    private function createResolver(array $rules): TagStyleResolver
    {
        /** @var TagStyleSettings $settings */
        $settings = SettingsTestHelper::createSettingsDummy(TagStyleSettings::class);
        $settings->rules = $rules;

        return new TagStyleResolver($settings);
    }

    public function testExactRuleIsFound(): void
    {
        $resolver = $this->createResolver([
            ['match_type' => 'exact', 'pattern' => 'Risk:Prüfen', 'color' => 'warning'],
        ]);

        $this->assertSame('badge text-bg-warning', $resolver->resolve('Risk:Prüfen', 'badge bg-primary'));
    }

    public function testPrefixRuleIsFound(): void
    {
        $resolver = $this->createResolver([
            ['match_type' => 'prefix', 'pattern' => 'Risk:', 'color' => 'info'],
        ]);

        $this->assertSame('badge text-bg-info', $resolver->resolve('Risk:Nicht-gelistet', 'badge bg-primary'));
    }

    public function testExactRuleWinsOverPrefixRule(): void
    {
        $resolver = $this->createResolver([
            ['match_type' => 'prefix', 'pattern' => 'Risk:', 'color' => 'info'],
            ['match_type' => 'exact', 'pattern' => 'Risk:Prüfen', 'color' => 'warning'],
        ]);

        $this->assertSame('badge text-bg-warning', $resolver->resolve('Risk:Prüfen', 'badge bg-primary'));
    }

    public function testLongestPrefixWins(): void
    {
        $resolver = $this->createResolver([
            ['match_type' => 'prefix', 'pattern' => 'Risk:', 'color' => 'info'],
            ['match_type' => 'prefix', 'pattern' => 'Risk:Nicht', 'color' => 'danger'],
        ]);

        $this->assertSame('badge text-bg-danger', $resolver->resolve('Risk:Nicht-gelistet', 'badge bg-primary'));
    }

    public function testFirstConfiguredRuleWinsOnTie(): void
    {
        $resolver = $this->createResolver([
            ['match_type' => 'exact', 'pattern' => 'Risk:Prüfen', 'color' => 'warning'],
            ['match_type' => 'exact', 'pattern' => 'Risk:Prüfen', 'color' => 'danger'],
        ]);

        $this->assertSame('badge text-bg-warning', $resolver->resolve('Risk:Prüfen', 'badge bg-primary'));
    }

    public function testUnknownTagUsesTheGivenDefault(): void
    {
        $resolver = $this->createResolver([
            ['match_type' => 'exact', 'pattern' => 'Risk:Prüfen', 'color' => 'warning'],
        ]);

        $this->assertSame('badge bg-primary', $resolver->resolve('SomeOtherTag', 'badge bg-primary'));
    }

    public function testTagWhitespaceIsTrimmedBeforeComparison(): void
    {
        $resolver = $this->createResolver([
            ['match_type' => 'exact', 'pattern' => 'Risk:Prüfen', 'color' => 'warning'],
        ]);

        $this->assertSame('badge text-bg-warning', $resolver->resolve('  Risk:Prüfen  ', 'badge bg-primary'));
    }

    public function testComparisonIsCaseSensitive(): void
    {
        $resolver = $this->createResolver([
            ['match_type' => 'exact', 'pattern' => 'Risk:Prüfen', 'color' => 'warning'],
        ]);

        $this->assertSame('badge bg-primary', $resolver->resolve('risk:prüfen', 'badge bg-primary'));
    }

    public function testUnknownColorValueIsRejectedAndFallsBackToDefault(): void
    {
        $resolver = $this->createResolver([
            ['match_type' => 'exact', 'pattern' => 'Risk:Prüfen', 'color' => 'not-a-real-color'],
        ]);

        $this->assertSame('badge bg-primary', $resolver->resolve('Risk:Prüfen', 'badge bg-primary'));
    }

    public function testDefaultColorKeepsTheGivenDefaultClass(): void
    {
        $resolver = $this->createResolver([
            ['match_type' => 'exact', 'pattern' => 'Risk:Prüfen', 'color' => 'default'],
        ]);

        $this->assertSame('badge bg-primary', $resolver->resolve('Risk:Prüfen', 'badge bg-primary'));
    }

    public function testEmptyRuleListKeepsThePreviousOutputUnchanged(): void
    {
        $resolver = $this->createResolver([]);

        $this->assertSame('badge bg-primary badge-table', $resolver->resolve('Anything', 'badge bg-primary badge-table'));
    }

    public function testMatchedColorReplacesOnlyTheColorTokenAndKeepsOtherClasses(): void
    {
        $resolver = $this->createResolver([
            ['match_type' => 'exact', 'pattern' => 'Risk:Prüfen', 'color' => 'warning'],
        ]);

        $this->assertSame(
            'badge badge-table text-bg-warning',
            $resolver->resolve('Risk:Prüfen', 'badge bg-primary badge-table')
        );
    }
}
