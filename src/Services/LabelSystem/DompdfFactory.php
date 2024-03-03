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

namespace App\Services\LabelSystem;

use Dompdf\Dompdf;
use Jbtronics\DompdfFontLoaderBundle\Services\DompdfFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: DompdfFactoryInterface::class)]
class DompdfFactory implements DompdfFactoryInterface
{
    public function __construct(private readonly string $fontDirectory, private readonly string $tmpDirectory)
    {
        //Create folder if it does not exist
        $this->createDirectoryIfNotExisting($this->fontDirectory);
        $this->createDirectoryIfNotExisting($this->tmpDirectory);
    }

    private function createDirectoryIfNotExisting(string $path): void
    {
        if (!is_dir($path)) {
            if (!mkdir($concurrentDirectory = $path, 0777, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
    }

    public function create(): Dompdf
    {
        return new Dompdf([
            'fontDir' => $this->fontDirectory,
            'fontCache' => $this->fontDirectory,
            'tempDir' => $this->tmpDirectory,
        ]);
    }
}