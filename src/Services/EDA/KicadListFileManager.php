<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\EDA;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class KicadListFileManager
{
    private const FOOTPRINTS_PATH = '/public/kicad/footprints.txt';
    private const SYMBOLS_PATH = '/public/kicad/symbols.txt';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getFootprintsContent(): string
    {
        return $this->readFile(self::FOOTPRINTS_PATH);
    }

    public function getSymbolsContent(): string
    {
        return $this->readFile(self::SYMBOLS_PATH);
    }

    public function save(string $footprints, string $symbols): void
    {
        $this->writeFile(self::FOOTPRINTS_PATH, $this->normalizeContent($footprints));
        $this->writeFile(self::SYMBOLS_PATH, $this->normalizeContent($symbols));
    }

    private function readFile(string $path): string
    {
        $fullPath = $this->projectDir . $path;

        if (!is_file($fullPath)) {
            return '';
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw new RuntimeException(sprintf('Failed to read KiCad list file "%s".', $fullPath));
        }

        return $content;
    }

    private function writeFile(string $path, string $content): void
    {
        $fullPath = $this->projectDir . $path;
        $tmpPath = $fullPath . '.tmp';

        if (file_put_contents($tmpPath, $content, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Failed to write KiCad list file "%s".', $fullPath));
        }

        if (!rename($tmpPath, $fullPath)) {
            @unlink($tmpPath);
            throw new RuntimeException(sprintf('Failed to replace KiCad list file "%s".', $fullPath));
        }
    }

    private function normalizeContent(string $content): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);

        if ($normalized !== '' && !str_ends_with($normalized, "\n")) {
            $normalized .= "\n";
        }

        return $normalized;
    }
}
