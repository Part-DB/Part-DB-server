<?php

namespace App\Twig;

use App\Entity\Attachments\Attachment;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Devices\Device;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Services\ElementTypeNameGenerator;
use App\Services\EntityURLGenerator;
use App\Services\Trees\TreeViewGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigTest;

final class EntityExtension extends AbstractExtension
{
    protected EntityURLGenerator $entityURLGenerator;
    protected TreeViewGenerator $treeBuilder;
    private ElementTypeNameGenerator $nameGenerator;

    public function __construct(EntityURLGenerator $entityURLGenerator, TreeViewGenerator $treeBuilder, ElementTypeNameGenerator $elementTypeNameGenerator)
    {
        $this->entityURLGenerator = $entityURLGenerator;
        $this->treeBuilder = $treeBuilder;
        $this->nameGenerator = $elementTypeNameGenerator;
    }

    public function getTests(): array
    {
        return [
            /* Checks if the given variable is an entitity (instance of AbstractDBElement) */
            new TwigTest('entity', static function ($var) {
                return $var instanceof AbstractDBElement;
            }),
        ];
    }

    public function getFunctions(): array
    {
        return [
            /* Returns a string representation of the given entity */
            new TwigFunction('entity_type', [$this, 'getEntityType']),
            /* Returns the URL to the given entity */
            new TwigFunction('entity_url', [$this, 'generateEntityURL']),
            /* Generates a JSON array of the given tree */
            new TwigFunction('tree_data', [$this, 'treeData']),

            /* Gets a human readable label for the type of the given entity */
            new TwigFunction('entity_type_label', [$this->nameGenerator, 'getLocalizedTypeLabel']),
        ];
    }

    public function treeData(AbstractDBElement $element, string $type = 'newEdit'): string
    {
        $tree = $this->treeBuilder->getTreeView(get_class($element), null, $type, $element);

        return json_encode($tree, JSON_THROW_ON_ERROR);
    }

    public function generateEntityURL(AbstractDBElement $entity, string $method = 'info'): string
    {
        return $this->entityURLGenerator->getURL($entity, $method);
    }

    public function getEntityType(object $entity): ?string
    {
        $map = [
            Part::class => 'part',
            Footprint::class => 'footprint',
            Storelocation::class => 'storelocation',
            Manufacturer::class => 'manufacturer',
            Category::class => 'category',
            Device::class => 'device',
            Attachment::class => 'attachment',
            Supplier::class => 'supplier',
            User::class => 'user',
            Group::class => 'group',
            Currency::class => 'currency',
            MeasurementUnit::class => 'measurement_unit',
            LabelProfile::class => 'label_profile',
        ];

        foreach ($map as $class => $type) {
            if ($entity instanceof $class) {
                return $type;
            }
        }

        return false;
    }
}