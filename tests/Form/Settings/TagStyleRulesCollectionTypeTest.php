<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Form\Settings;

use App\Form\Settings\TagStyleRulesCollectionType;
use App\Form\Settings\TagStyleRuleType;
use App\Services\Tools\TagStyleColor;
use App\Services\Tools\TagStyleMatchType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

final class TagStyleRulesCollectionTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [
            new ValidatorExtension(Validation::createValidator()),
            new PreloadedExtension([new TagStyleRulesCollectionType(), new TagStyleRuleType()], []),
        ];
    }

    /**
     * Regression test: setData() must not fail when the model data already contains rows, and the form must
     * remain usable afterwards (e.g. when the settings page is loaded a second time with persisted rules).
     * A previous version of this type ran the model-to-view conversion twice - once in a PRE_SET_DATA listener
     * and once via the model transformer that Symfony invokes automatically whenever setData() is called - which
     * crashed on the second pass, because match_type/color were already enum instances by then instead of the
     * strings the conversion expects.
     */
    public function testSetDataWithExistingRowsDoesNotCrash(): void
    {
        $form = $this->factory->create(TagStyleRulesCollectionType::class, [
            ['match_type' => 'exact', 'pattern' => 'important', 'color' => 'danger'],
        ]);

        $this->assertCount(1, $form);
        $this->assertSame(TagStyleMatchType::EXACT, $form->get('0')->get('matchType')->getData());
        $this->assertSame('important', $form->get('0')->get('pattern')->getData());
        $this->assertSame(TagStyleColor::DANGER, $form->get('0')->get('color')->getData());
    }

    public function testSubmitRoundTripsBackToModelData(): void
    {
        $form = $this->factory->create(TagStyleRulesCollectionType::class, []);

        $form->submit([
            ['matchType' => 'prefix', 'pattern' => 'obsolete-', 'color' => 'warning'],
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame(
            [['match_type' => 'prefix', 'pattern' => 'obsolete-', 'color' => 'warning']],
            $form->getData()
        );
    }
}
