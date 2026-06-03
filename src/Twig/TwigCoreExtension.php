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

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Twig\Attribute\AsTwigTest;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * The functionalities here extend the Twig with some core functions, which are independently of Part-DB.
 * @see \App\Tests\Twig\TwigCoreExtensionTest
 */
final readonly class TwigCoreExtension
{
    private NormalizerInterface $objectNormalizer;

    public function __construct()
    {
        $this->objectNormalizer = new ObjectNormalizer();
    }

    /**
     * Checks if the given variable is an instance of the given class/interface/enum. E.g. `x is instanceof('App\Entity\Parts\Part')`
     * @param  mixed  $var
     * @param  string  $instance
     * @return bool
     */
    #[AsTwigTest("instanceof")]
    public function testInstanceOf(mixed $var, string $instance): bool
    {
        if (!class_exists($instance) && !interface_exists($instance) && !enum_exists($instance)) {
            throw new \InvalidArgumentException(sprintf('The given class/interface/enum "%s" does not exist!', $instance));
        }

        return $var instanceof $instance;
    }

    /**
     * Checks if the given variable is an object. This can be used to check if a variable is an object, without knowing the exact class of the object. E.g. `x is object`
     * @param  mixed  $var
     * @return bool
     */
    #[AsTwigTest("object")]
    public function testObject(mixed $var): bool
    {
        return is_object($var);
    }

    /**
     * Checks if the given variable is an enum (instance of UnitEnum). This can be used to check if a variable is an enum, without knowing the exact class of the enum. E.g. `x is enum`
     * @param  mixed  $var
     * @return bool
     */
    #[AsTwigTest("enum")]
    public function testEnum(mixed $var): bool
    {
        return $var instanceof \UnitEnum;
    }

    #[AsTwigFilter('to_array')]
    public function toArray(object|array $object): array
    {
        //If it is already an array, we can just return it
        if(is_array($object)) {
            return $object;
        }

        return $this->objectNormalizer->normalize($object, null);
    }
}
