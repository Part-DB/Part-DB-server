<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Tests\Services\InfoProviderSystem\Providers;

use App\Exceptions\ProviderIDNotSupportedException;
use App\Services\AI\AIPlatformRegistry;
use App\Services\AI\AIPlatforms;
use App\Services\InfoProviderSystem\AIPartInfoExtractor;
use App\Services\InfoProviderSystem\DTOJsonSchemaConverter;
use App\Services\InfoProviderSystem\DTOs\UploadedDocument;
use App\Services\InfoProviderSystem\Providers\AIDocumentProvider;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use App\Services\InfoProviderSystem\UploadedDocumentStorage;
use App\Settings\InfoProviderSystem\AIFileExtractorSettings;
use App\Tests\SettingsTestHelper;
use Jbtronics\SettingsBundle\Manager\SettingsManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class AIDocumentProviderTest extends TestCase
{
    /** @var MessageBag[] The inputs the fake platform was invoked with */
    private array $invocations = [];

    private UploadedDocumentStorage $storage;
    private AIDocumentProvider $provider;

    protected function setUp(): void
    {
        $this->invocations = [];

        $converter = $this->createMock(ResultConverterInterface::class);
        $converter->method('supports')->willReturn(true);
        $converter->method('convert')->willReturnCallback(static fn() => new ObjectResult([
            'name' => 'BC547',
            'description' => 'NPN transistor',
            'manufacturer' => 'Example Semiconductors',
            'mpn' => 'BC547B',
            'footprint' => 'TO-92',
        ]));

        $platform = new class($converter, $this->invocations) implements PlatformInterface {
            public function __construct(private readonly ResultConverterInterface $converter, private array &$invocations)
            {
            }

            public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
            {
                $this->invocations[] = $input;
                return new DeferredResult($this->converter, new InMemoryRawResult([], [], (object) []));
            }

            public function getModelCatalog(): ModelCatalogInterface
            {
                throw new \LogicException('Not needed for this test');
            }
        };

        $settingsManager = $this->createMock(SettingsManagerInterface::class);
        $settingsManager->method('get')->willReturn(new class {
            public function isAIPlatformEnabled(): bool
            {
                return true;
            }
        });
        $registry = new AIPlatformRegistry($settingsManager, [AIPlatforms::OPENROUTER->toServiceTagName() => $platform]);

        $settings = SettingsTestHelper::createSettingsDummy(AIFileExtractorSettings::class);
        $settings->platform = AIPlatforms::OPENROUTER;
        $settings->model = 'a/model';

        $this->storage = new UploadedDocumentStorage(new ArrayAdapter());
        $this->provider = new AIDocumentProvider(
            $settings,
            new AIPartInfoExtractor($registry, new DTOJsonSchemaConverter()),
            new DTOJsonSchemaConverter(),
            $this->storage,
            new ArrayAdapter(),
        );
    }

    public function testGetDetails(): void
    {
        $token = $this->storage->store(new UploadedDocument('bc547.pdf', 'BC547 NPN general purpose transistor'));

        $dto = $this->provider->getDetails($token);

        self::assertSame(AIDocumentProvider::PROVIDER_KEY, $dto->provider_key);
        self::assertSame($token, $dto->provider_id);
        self::assertSame('BC547', $dto->name);
        self::assertSame('BC547B', $dto->mpn);
        self::assertSame('TO-92', $dto->footprint);

        //The text and the filename of the document must be passed to the model
        self::assertCount(1, $this->invocations);
        $userMessage = $this->invocations[0]->getUserMessage()?->asText() ?? '';
        self::assertStringContainsString('BC547 NPN general purpose transistor', $userMessage);
        self::assertStringContainsString('bc547.pdf', $userMessage);
    }

    public function testContextIsPassedToTheModel(): void
    {
        $token = $this->storage->store(new UploadedDocument('bc547.pdf', 'BC547A BC547B BC547C', 'Use the variant BC547C'));

        $this->provider->getDetails($token);

        $messages = array_map(static fn($m) => method_exists($m, 'asText') ? $m->asText() : '',
            $this->invocations[0]->getMessages());
        self::assertContains("Additional context given by the user, which has priority over the rules above:\n\nUse the variant BC547C", $messages);
    }

    public function testNoContextMessageWithoutContext(): void
    {
        $token = $this->storage->store(new UploadedDocument('bc547.pdf', 'BC547', '   '));

        $this->provider->getDetails($token);

        //System prompt and document only
        self::assertCount(2, $this->invocations[0]->getMessages());
    }

    public function testResultIsCached(): void
    {
        $token = $this->storage->store(new UploadedDocument('bc547.pdf', 'BC547'));

        $this->provider->getDetails($token);
        $this->provider->getDetails($token);
        self::assertCount(1, $this->invocations);

        $this->provider->getDetails($token, [InfoProviderInterface::OPTION_NO_CACHE => true]);
        self::assertCount(2, $this->invocations);
    }

    public function testUnknownDocument(): void
    {
        self::assertSame([], $this->provider->searchByKeyword('0123456789abcdef'));

        $this->expectException(ProviderIDNotSupportedException::class);
        $this->provider->getDetails('0123456789abcdef');
    }

    public function testSearchByToken(): void
    {
        $token = $this->storage->store(new UploadedDocument('bc547.pdf', 'BC547'));

        $results = $this->provider->searchByKeyword($token);
        self::assertCount(1, $results);
        self::assertSame('BC547', $results[0]->name);
    }
}
