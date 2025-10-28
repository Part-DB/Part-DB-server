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

namespace App\Tests\Controller\AdminPages;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use App\Entity\LabelSystem\LabelProfile;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class LabelProfileController extends AbstractAdminController
{
    protected static string $base_path = '/en/label_profile';
    protected static string $entity_class = LabelProfile::class;

    /**
     * Tests if deleting an entity is working.
     */
    #[DataProvider('deleteDataProvider')]
    #[Group('slow')]
    public function testDeleteEntity(string $user, bool $delete): void
    {
        //Test read access
        $client = static::createClient([], [
            'PHP_AUTH_USER' => $user,
            'PHP_AUTH_PW' => 'test',
        ]);

        $client->catchExceptions(false);
        if (false === $delete) {
            $this->expectException(AccessDeniedException::class);
        }

        //Test read/list access by access /new overview page
        $client->request('DELETE', static::$base_path.'/3');

        //Page is redirected to '/new', when delete was successful
        $this->assertSame($delete, $client->getResponse()->isRedirect(static::$base_path.'/new'));
        $this->assertSame($delete, !$client->getResponse()->isForbidden(), 'Permission Checking not working!');
    }
}
