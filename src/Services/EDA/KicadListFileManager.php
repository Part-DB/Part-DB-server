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
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Manages the KiCad footprints and symbols list files, including reading, writing and ensuring their existence.
 */
final class KicadListFileManager implements CacheWarmerInterface
{
    private const FOOTPRINTS_PATH = '/public/kicad/footprints.txt';
    private const SYMBOLS_PATH = '/public/kicad/symbols.txt';
    private const CUSTOM_FOOTPRINTS_PATH = '/public/kicad/footprints_custom.txt';
    private const CUSTOM_SYMBOLS_PATH = '/public/kicad/symbols_custom.txt';

    private const CUSTOM_TEMPLATE = <<<'EOT'
        # Custom KiCad autocomplete entries. One entry per line.

        EOT;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getFootprintsContent(): string
    {
        return $this->readFile(self::FOOTPRINTS_PATH);
    }

    public function getCustomFootprintsContent(): string
    {
        //Ensure that the custom file exists, so that the UI can always display it without error.
        $this->createCustomFileIfNotExists(self::CUSTOM_FOOTPRINTS_PATH);
        return $this->readFile(self::CUSTOM_FOOTPRINTS_PATH);
    }

    public function getSymbolsContent(): string
    {
        return $this->readFile(self::SYMBOLS_PATH);
    }

    public function getCustomSymbolsContent(): string
    {
        //Ensure that the custom file exists, so that the UI can always display it without error.
        $this->createCustomFileIfNotExists(self::CUSTOM_SYMBOLS_PATH);
        return $this->readFile(self::CUSTOM_SYMBOLS_PATH);
    }

    public function saveCustom(string $footprints, string $symbols): void
    {
        $this->writeFile(self::CUSTOM_FOOTPRINTS_PATH, $this->normalizeContent($footprints));
        $this->writeFile(self::CUSTOM_SYMBOLS_PATH, $this->normalizeContent($symbols));
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

    private function createCustomFileIfNotExists(string $path): void
    {
        $fullPath = $this->projectDir . $path;

        if (!is_file($fullPath)) {
            if (file_put_contents($fullPath, self::CUSTOM_TEMPLATE, LOCK_EX) === false) {
                throw new RuntimeException(sprintf('Failed to create custom footprints file "%s".', $fullPath));
            }
        }
    }

    /**
     * Ensures that the custom footprints and symbols files exist, so that the UI can always display them without error.
     * @return void
     */
    public function createCustomFilesIfNotExist(): void
    {
        $this->createCustomFileIfNotExists(self::CUSTOM_FOOTPRINTS_PATH);
        $this->createCustomFileIfNotExists(self::CUSTOM_SYMBOLS_PATH);
    }


    public function isOptional(): bool
    {
        return false;
    }

    /**
     * Ensure that the custom footprints and symbols files exist and generate them on cache warmup, so that the frontend
     * can always display them without error, even if the user has not yet visited the settings page.
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $this->createCustomFilesIfNotExist();
        return [];
    }
}
