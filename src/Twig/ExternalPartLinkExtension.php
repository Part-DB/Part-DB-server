<?php

declare(strict_types=1);

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
namespace App\Twig;

use App\Entity\Parts\Part;
use App\Services\Tools\ExternalPartLinkResolver;
use App\Services\Tools\ResolvedExternalPartLink;
use Twig\Attribute\AsTwigFunction;

final readonly class ExternalPartLinkExtension
{
    public function __construct(private ExternalPartLinkResolver $resolver)
    {
    }

    /**
     * Returns the resolved, ready-to-render external links for the given part, in the order
     * they are configured in.
     * @return ResolvedExternalPartLink[]
     */
    #[AsTwigFunction('external_part_links')]
    public function externalPartLinks(Part $part): array
    {
        return $this->resolver->resolve($part);
    }
}
