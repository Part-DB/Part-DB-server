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


namespace App\EventListener;

use App\Doctrine\Functions\Natsort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * This is a workaround to the fact that we can not inject parameters into doctrine custom functions.
 * Therefore we use this event listener to call the static function on the custom function, to inject the value, before
 * any NATSORT function is called.
 */
#[AsEventListener]
class AllowSlowNaturalSortListener
{
    public function __construct(
        #[Autowire(param: 'partdb.db.emulate_natural_sort')]
        private readonly bool $allowNaturalSort)
    {
    }

    public function __invoke(RequestEvent $event)
    {
        Natsort::allowSlowNaturalSort($this->allowNaturalSort);
    }
}