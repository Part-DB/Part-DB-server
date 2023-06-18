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

use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * The functionalities here extend the Twig with some core functions, which are independently of Part-DB.
 * @see \App\Tests\Twig\TwigCoreExtensionTest
 */
final class TwigCoreExtension extends AbstractExtension
{
    public function __construct(protected ObjectNormalizer $objectNormalizer)
    {
    }

    public function getFunctions()
    {
        return [
            /* Returns the enum cases as values */
            new TwigFunction('enum_cases', [$this, 'getEnumCases']),
        ];
    }

    public function getTests(): array
    {
        return [
            /*
             * Checks if a given variable is an instance of a given class. E.g. ` x is instanceof('App\Entity\Parts\Part')`
             */
            new TwigTest('instanceof', static fn($var, $instance) => $var instanceof $instance),
            /* Checks if a given variable is an object. E.g. `x is object` */
            new TwigTest('object', static fn($var): bool => is_object($var)),
        ];
    }

    /**
     * @param  string  $enum_class
     * @phpstan-param class-string $enum_class
     */
    public function getEnumCases(string $enum_class): array
    {
        if (!enum_exists($enum_class)) {
            throw new \InvalidArgumentException(sprintf('The given class "%s" is not an enum!', $enum_class));
        }

        return ($enum_class)::cases();
    }

    public function getFilters(): array
    {
        return [
            /* Converts the given object to an array representation of the public/accessible properties  */
            new TwigFilter('to_array', fn($object) => $this->toArray($object)),
        ];
    }

    public function toArray(object|array $object): array
    {
        //If it is already an array, we can just return it
        if(is_array($object)) {
            return $object;
        }

        return $this->objectNormalizer->normalize($object, null);
    }
}
