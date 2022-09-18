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
use App\Services\EntityURLGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

class EntityExtension extends AbstractExtension
{
    protected $entityURLGenerator;

    public function __construct(EntityURLGenerator $entityURLGenerator)
    {
        $this->entityURLGenerator = $entityURLGenerator;
    }

    public function getFilters(): array
    {
        return [

        ];
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
            new TwigFunction('entity_url', [$this, 'generateEntityURL']),
        ];
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