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

use App\Form\Settings\ExternalPartLinkRulesCollectionType;
use App\Form\Settings\ExternalPartLinkRuleType;
use App\Services\Tools\ExternalPartLinkIcon;
use App\Services\Tools\ExternalPartLinkSourceType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ExternalPartLinkRulesCollectionTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return [
            new ValidatorExtension(Validation::createValidator()),
            new PreloadedExtension([
                new ExternalPartLinkRulesCollectionType($translator),
                new ExternalPartLinkRuleType(),
            ], []),
        ];
    }

    /**
     * Regression test: setData() must not fail when the model data already contains rows, and the form must
     * remain usable afterwards (e.g. when the settings page is loaded a second time with persisted links).
     * A previous version of this type ran the model-to-view conversion twice - once in a PRE_SET_DATA listener
     * and once via the model transformer that Symfony invokes automatically whenever setData() is called - which
     * crashed on the second pass, because sourceType/icon were already enum instances by then instead of the
     * strings the conversion expects (see the identical bug fixed in TagStyleRulesCollectionType).
     */
    public function testSetDataWithExistingRowsDoesNotCrash(): void
    {
        $form = $this->factory->create(ExternalPartLinkRulesCollectionType::class, [
            [
                'name' => 'Octopart Search',
                'source_type' => 'template',
                'source' => 'https://octopart.com/search?q={mpn}',
                'icon' => 'search',
                'open_new_tab' => true,
                'enabled' => true,
            ],
        ]);

        $this->assertCount(1, $form);
        $this->assertSame('Octopart Search', $form->get('0')->get('name')->getData());
        $this->assertSame(ExternalPartLinkSourceType::TEMPLATE, $form->get('0')->get('sourceType')->getData());
        $this->assertSame(ExternalPartLinkIcon::SEARCH, $form->get('0')->get('icon')->getData());
    }

    public function testSubmitRoundTripsBackToModelData(): void
    {
        $form = $this->factory->create(ExternalPartLinkRulesCollectionType::class, []);

        $form->submit([
            [
                'name' => 'Datasheet',
                'sourceType' => 'parameter',
                'source' => 'Datasheet URL',
                'icon' => 'file',
                'openNewTab' => true,
                'enabled' => true,
            ],
        ]);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame([[
            'name' => 'Datasheet',
            'source_type' => 'parameter',
            'source' => 'Datasheet URL',
            'icon' => 'file',
            'open_new_tab' => true,
            'enabled' => true,
        ]], $form->getData());
    }
}
