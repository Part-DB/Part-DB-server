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
namespace App\Twig;

use App\Entity\LogSystem\AbstractLogEntry;
use App\Services\LogSystem\LogDataFormatter;
use App\Services\LogSystem\LogDiffFormatter;
use Twig\Attribute\AsTwigFunction;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final readonly class LogExtension
{

    public function __construct(private LogDataFormatter $logDataFormatter, private LogDiffFormatter $logDiffFormatter)
    {
    }

    #[AsTwigFunction(name: 'format_log_data', isSafe: ['html'])]
    public function formatLogData(mixed $data, AbstractLogEntry $logEntry, string $fieldName): string
    {
        return $this->logDataFormatter->formatData($data, $logEntry, $fieldName);
    }

    #[AsTwigFunction(name: 'format_log_diff', isSafe: ['html'])]
    public function formatLogDiff(mixed $old_data, mixed $new_data): string
    {
        return $this->logDiffFormatter->formatDiff($old_data, $new_data);
    }
}
