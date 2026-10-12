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
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

final class AIDocumentProviderTest extends TestCase
{
    /** @var MessageBag[] The inputs the fake platform was invoked with */
    private array $invocations = [];

    private UploadedDocumentStorage $storage;
    private string $fileDirectory;
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

        $this->fileDirectory = sys_get_temp_dir().'/partdb_test_'.bin2hex(random_bytes(8));
        $this->storage = new UploadedDocumentStorage(new ArrayAdapter(), $this->fileDirectory);
        $this->provider = new AIDocumentProvider(
            $settings,
            new AIPartInfoExtractor($registry, new DTOJsonSchemaConverter()),
            new DTOJsonSchemaConverter(),
            $this->storage,
            new ArrayAdapter(),
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->fileDirectory);
    }

    /**
     * Stores a document without text, whose file has to be sent to the model
     */
    private function storeFileDocument(string $filename, string $content, string $mimeType): string
    {
        $path = $this->fileDirectory.'_upload';
        file_put_contents($path, $content);

        return $this->storage->store(new UploadedDocument($filename, '', null, strlen($content), $mimeType, hash('xxh3', $content)), new File($path));
    }

    /**
     * @return ContentInterface[] The content of the user message of the first invocation
     */
    private function getSentContent(): array
    {
        self::assertCount(1, $this->invocations);
        return $this->invocations[0]->getUserMessage()?->getContent() ?? [];
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

    public function testImageIsSentToTheModel(): void
    {
        $token = $this->storeFileDocument('photo.png', 'PNG image content', 'image/png');

        self::assertSame('BC547', $this->provider->getDetails($token)->name);

        $content = $this->getSentContent();
        self::assertCount(2, $content);
        self::assertInstanceOf(Text::class, $content[0]);
        self::assertStringContainsString('photo.png', $content[0]->getText());
        self::assertInstanceOf(Image::class, $content[1]);
        self::assertSame('image/png', $content[1]->getFormat());
        self::assertSame('PNG image content', $content[1]->asBinary());

        //The prompt must not talk about extracted text
        self::assertStringContainsString('from the attached file', $this->invocations[0]->getSystemMessage()?->getContent() ?? '');
    }

    public function testPdfIsSentAsDocument(): void
    {
        $token = $this->storeFileDocument('scan.pdf', '%PDF-1.4 scanned', 'application/pdf');

        $this->provider->getDetails($token);

        $content = $this->getSentContent();
        self::assertInstanceOf(Document::class, $content[1]);
        self::assertSame('application/pdf', $content[1]->getFormat());
        self::assertSame('%PDF-1.4 scanned', $content[1]->asBinary());
    }

    public function testExtractedTextIsSentWithoutFile(): void
    {
        $token = $this->storage->store(new UploadedDocument('bc547.pdf', 'BC547 NPN'));

        $this->provider->getDetails($token);

        $content = $this->getSentContent();
        self::assertCount(1, $content);
        self::assertInstanceOf(Text::class, $content[0]);
        self::assertStringContainsString('from the text extracted from a document', $this->invocations[0]->getSystemMessage()?->getContent() ?? '');
    }

    public function testMissingFileOfDocumentWithoutText(): void
    {
        //No text and no file, there is nothing to send
        $token = $this->storage->store(new UploadedDocument('scan.pdf', '', fileMimeType: 'application/pdf'));

        $this->expectException(ProviderIDNotSupportedException::class);
        $this->provider->getDetails($token);
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
