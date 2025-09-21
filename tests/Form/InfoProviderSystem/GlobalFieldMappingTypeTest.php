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

declare(strict_types=1);

namespace App\Tests\Form\InfoProviderSystem;

use App\Form\InfoProviderSystem\GlobalFieldMappingType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * @group slow
 * @group DB
 */
class GlobalFieldMappingTypeTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->formFactory = static::getContainer()->get(FormFactoryInterface::class);
    }

    public function testFormCreation(): void
    {
        $form = $this->formFactory->create(GlobalFieldMappingType::class, null, [
            'field_choices' => [
                'MPN' => 'mpn',
                'Name' => 'name'
            ],
            'csrf_protection' => false
        ]);

        $this->assertTrue($form->has('field_mappings'));
        $this->assertTrue($form->has('prefetch_details'));
        $this->assertTrue($form->has('submit'));
    }

    public function testFormOptions(): void
    {
        $form = $this->formFactory->create(GlobalFieldMappingType::class, null, [
            'field_choices' => [],
            'csrf_protection' => false
        ]);

        $view = $form->createView();
        $this->assertFalse($view['prefetch_details']->vars['required']);
    }
}