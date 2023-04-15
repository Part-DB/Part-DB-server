<?php
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

use App\Entity\Base\AbstractDBElement;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigTest;

/**
 * The functionalities here extend the Twig with some core functions, which are independently of Part-DB.
 */
final class TwigCoreExtension extends AbstractExtension
{
    protected ObjectNormalizer $objectNormalizer;

    public function __construct(ObjectNormalizer $objectNormalizer)
    {
        $this->objectNormalizer = $objectNormalizer;
    }

    public function getTests(): array
    {
        return [
            /*
             * Checks if a given variable is an instance of a given class. E.g. ` x is instanceof('App\Entity\Parts\Part')`
             */
            new TwigTest('instanceof', static function ($var, $instance) {
                return $var instanceof $instance;
            }),
            /* Checks if a given variable is an object. E.g. `x is object` */
            new TwigTest('object', static function ($var) {
                return is_object($var);
            }),
        ];
    }

    public function getFilters(): array
    {
        return [
            /* Converts the given object to an array representation of the public/accessible properties  */
            new TwigFilter('to_array', [$this, 'toArray']),
        ];
    }

    public function toArray($object)
    {
        if(! is_object($object) && ! is_array($object)) {
            throw new \InvalidArgumentException('The given variable is not an object or array!');
        }

        //If it is already an array, we can just return it
        if(is_array($object)) {
            return $object;
        }

        return $this->objectNormalizer->normalize($object, null);
    }
}