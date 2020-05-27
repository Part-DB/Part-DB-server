<?php


namespace App\Twig;


use App\Services\ElementTypeNameGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TypeLabelExtension extends AbstractExtension
{
    private $nameGenerator;

    public function __construct(ElementTypeNameGenerator $elementTypeNameGenerator)
    {
        $this->nameGenerator = $elementTypeNameGenerator;
    }

    public function getFunctions()
    {
        return [
                new TwigFunction('elementType', [$this->nameGenerator, 'getLocalizedTypeLabel']),
                new TwigFunction('elementTypeName', [$this->nameGenerator, 'getTypeNameCombination']),
            ];
    }
}