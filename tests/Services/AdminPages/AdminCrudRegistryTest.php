<?php

declare(strict_types=1);

namespace App\Tests\Services\AdminPages;

use App\Entity\Attachments\AttachmentType;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\PartCustomState;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\ProjectSystem\Project;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Services\AdminPages\AdminCrudRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AdminCrudRegistryTest extends KernelTestCase
{
    private AdminCrudRegistry $registry;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->registry = self::getContainer()->get(AdminCrudRegistry::class);
    }

    public function testGetAllConfigsReturns13Entries(): void
    {
        $configs = $this->registry->getAllConfigs();
        self::assertCount(13, $configs);
    }

    public function testExpectedEntitiesArePresent(): void
    {
        $expected = [
            Category::class,
            Footprint::class,
            Manufacturer::class,
            StorageLocation::class,
            Supplier::class,
            MeasurementUnit::class,
            PartCustomState::class,
            AttachmentType::class,
            Currency::class,
            LabelProfile::class,
            Group::class,
            Project::class,
            User::class,
        ];

        foreach ($expected as $class) {
            self::assertTrue(
                $this->registry->hasConfig($class),
                "Expected $class to have an #[AdminCrud] config"
            );
        }
    }

    public function testLabelProfileHasNoParameters(): void
    {
        $config = $this->registry->getConfig(LabelProfile::class);
        self::assertFalse($config->hasParameters());
        self::assertFalse($config->showParametersTab());
    }

    public function testPartCustomStateShowParametersTabIsFalse(): void
    {
        $config = $this->registry->getConfig(PartCustomState::class);
        self::assertTrue($config->hasParameters());
        self::assertFalse($config->showParametersTab());
    }

    public function testUserHasNoParameters(): void
    {
        $config = $this->registry->getConfig(User::class);
        self::assertFalse($config->hasParameters());
    }

    public function testCategoryHasAttachmentsAndParameters(): void
    {
        $config = $this->registry->getConfig(Category::class);
        self::assertTrue($config->hasAttachments());
        self::assertTrue($config->hasParameters());
        self::assertSame('category', $config->getRouteBase());
    }

    public function testConfigCachingReturnsConsistentResults(): void
    {
        $first = $this->registry->getAllConfigs();
        $second = $this->registry->getAllConfigs();
        self::assertSame($first, $second);
    }
}
