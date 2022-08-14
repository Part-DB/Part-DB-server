<?php
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

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Services\TranslationExtractor;

use App\Services\PermissionResolver;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * The purpose of this class is to extract label attributes out of our permissions.yaml structure,
 * so they can be translated.
 */
final class PermissionExtractor implements ExtractorInterface
{
    private $permission_structure;
    private $finished = false;

    public function __construct(PermissionResolver $resolver)
    {
        $this->permission_structure = $resolver->getPermissionStructure();
    }

    /**
     * Extracts translation messages from files, a file or a directory to the catalogue.
     *
     * @param string|array     $resource  Files, a file or a directory
     * @param MessageCatalogue $catalogue The catalogue
     */
    public function extract($resource, MessageCatalogue $catalogue): void
    {
        if (!$this->finished) {
            //Extract for every group...
            foreach ($this->permission_structure['groups'] as $group) {
                if (isset($group['label'])) {
                    $catalogue->add([
                        $group['label'] => '__'.$group['label'],
                    ]);
                }
            }

            //... every permission
            foreach ($this->permission_structure['perms'] as $perm) {
                if (isset($perm['label'])) {
                    $catalogue->add([
                        $perm['label'] => '__'.$perm['label'],
                    ]);
                }

                //... and all of their operations
                foreach ($perm['operations'] as $op) {
                    if (isset($op['label'])) {
                        $catalogue->add([
                            $op['label'] => '__'.$op['label'],
                        ]);
                    }
                }
            }

            $this->finished = true;
        }
    }

    /**
     * Sets the prefix that should be used for new found messages.
     *
     * @param  string  $prefix The prefix
     */
    public function setPrefix(string $prefix): string
    {
        return '';
    }
}
