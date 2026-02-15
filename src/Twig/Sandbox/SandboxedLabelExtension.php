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


namespace App\Twig\Sandbox;

use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Parts\Part;
use App\Repository\AbstractPartsContainingRepository;
use App\Services\LabelSystem\LabelTextReplacer;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class SandboxedLabelExtension extends AbstractExtension
{
    public function __construct(private readonly LabelTextReplacer $labelTextReplacer, private readonly EntityManagerInterface $em)
    {

    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('placeholder', fn(string $text, object $label_target) => $this->labelTextReplacer->handlePlaceholderOrReturnNull($text, $label_target)),

            new TwigFunction("associated_parts", $this->associatedParts(...)),
            new TwigFunction("associated_parts_count", $this->associatedPartsCount(...)),
            new TwigFunction("associated_parts_r", $this->associatedPartsRecursive(...)),
            new TwigFunction("associated_parts_count_r", $this->associatedPartsCountRecursive(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('placeholders', fn(string $text, object $label_target) => $this->labelTextReplacer->replace($text, $label_target)),
        ];
    }

    /**
     * Returns all parts associated with the given element.
     * @param  AbstractPartsContainingDBElement  $element
     * @return Part[]
     */
    public function associatedParts(AbstractPartsContainingDBElement $element): array
    {
        /** @var AbstractPartsContainingRepository $repo */
        $repo = $this->em->getRepository($element::class);
        return $repo->getParts($element);
    }

    public function associatedPartsCount(AbstractPartsContainingDBElement $element): int
    {
        /** @var AbstractPartsContainingRepository $repo */
        $repo = $this->em->getRepository($element::class);
        return $repo->getPartsCount($element);
    }

    public function associatedPartsRecursive(AbstractPartsContainingDBElement $element): array
    {
        /** @var AbstractPartsContainingRepository $repo */
        $repo = $this->em->getRepository($element::class);
        return $repo->getPartsRecursive($element);
    }

    public function associatedPartsCountRecursive(AbstractPartsContainingDBElement $element): int
    {
        /** @var AbstractPartsContainingRepository $repo */
        $repo = $this->em->getRepository($element::class);
        return $repo->getPartsCountRecursive($element);
    }
}
