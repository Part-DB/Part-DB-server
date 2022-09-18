<?php

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

    public function getFilters()
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