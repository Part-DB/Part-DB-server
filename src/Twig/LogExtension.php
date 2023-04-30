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

namespace App\Twig;

use App\Services\LogSystem\LogDataFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LogExtension extends AbstractExtension
{
    public function __construct(LogDataFormatter $logDataFormatter)
    {
        $this->logDataFormatter = $logDataFormatter;
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('format_log_data', [$this->logDataFormatter, 'formatData'], ['is_safe' => ['html']])
        ];
    }
}