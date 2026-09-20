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

use App\Services\AI\AIPlatformRegistry;
use App\Services\AI\AIPlatforms;
use App\Services\InfoProviderSystem\DTOJsonSchemaConverter;
use App\Services\InfoProviderSystem\CreateFromUrlHelper;
use App\Services\InfoProviderSystem\Providers\AIWebProvider;
use App\Services\InfoProviderSystem\SubmittedPageStorage;
use App\Settings\InfoProviderSystem\AIExtractorSettings;
use App\Tests\SettingsTestHelper;
use Jbtronics\SettingsBundle\Manager\SettingsManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see AIWebProvider
 */
final class AIWebProviderTest extends TestCase
{
    /**
     * Builds a platform whose result fails when it is read, which is how a provider error really arrives:
     * invoke() only hands out a deferred result, and the request is carried out when that result is used.
     */
    private function platformFailingOnRead(\Throwable $failure): PlatformInterface
    {
        $converter = $this->createMock(ResultConverterInterface::class);
        $converter->method('supports')->willReturn(true);
        $converter->method('convert')->willThrowException($failure);

        $deferred = new DeferredResult($converter, new InMemoryRawResult([], [], (object) []));

        return new class($deferred) implements PlatformInterface {
            public function __construct(private readonly DeferredResult $deferred)
            {
            }

            public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
            {
                return $this->deferred;
            }

            public function getModelCatalog(): ModelCatalogInterface
            {
                throw new \LogicException('Not needed for this test');
            }
        };
    }


    /**
     * The same, but with a real HTTP response behind the result, so that the description of the failure can be
     * checked - a gateway puts the actual reason into the body, not into the message of the exception.
     */
    private function platformFailingWithResponse(\Throwable $failure, int $status, string $body): PlatformInterface
    {
        $converter = $this->createMock(ResultConverterInterface::class);
        $converter->method('supports')->willReturn(true);
        $converter->method('convert')->willThrowException($failure);

        $response = (new MockHttpClient(new MockResponse($body, ['http_code' => $status])))
            ->request('POST', 'https://invalid.invalid/chat');

        $deferred = new DeferredResult($converter, new RawHttpResult($response));

        return new class($deferred) implements PlatformInterface {
            public function __construct(private readonly DeferredResult $deferred)
            {
            }

            public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
            {
                return $this->deferred;
            }

            public function getModelCatalog(): ModelCatalogInterface
            {
                throw new \LogicException('Not needed for this test');
            }
        };
    }

    /**
     * callLLM() only uses the platform registry, the settings and the schema converter. Building the whole
     * provider would drag in three more services which have nothing to do with this, so the instance is created
     * without its constructor and only those three are filled in.
     */
    private function provider(PlatformInterface $platform): AIWebProvider
    {
        //The registry is final, so it is built for real: one registered platform, reported as enabled
        $settingsManager = $this->createMock(SettingsManagerInterface::class);
        $settingsManager->method('get')->willReturn(new class {
            public function isAIPlatformEnabled(): bool
            {
                return true;
            }
        });
        $registry = new AIPlatformRegistry($settingsManager, [AIPlatforms::OPENROUTER->toServiceTagName() => $platform]);

        $settings = SettingsTestHelper::createSettingsDummy(AIExtractorSettings::class);
        $settings->platform = AIPlatforms::OPENROUTER;
        $settings->model = 'a/model';

        $provider = (new \ReflectionClass(AIWebProvider::class))->newInstanceWithoutConstructor();

        foreach (['AIPlatformRegistry' => $registry, 'settings' => $settings,
                  'jsonSchemaConverter' => new DTOJsonSchemaConverter()] as $name => $value) {
            $property = new \ReflectionProperty(AIWebProvider::class, $name);
            $property->setValue($provider, $value);
        }

        return $provider;
    }

    public function testAProviderErrorWhileReadingTheResultIsWrapped(): void
    {
        //A rejected model, an exhausted quota or an invalid key all arrive like this. Before, the result was
        //read outside the try block, so the exception escaped unhandled and ended the request with a 500 -
        //even though both the search page and the "create from URL" page know how to report a RuntimeException.
        $provider = $this->provider($this->platformFailingOnRead(new BadRequestException('Provider returned error')));

        $callLLM = new \ReflectionMethod(AIWebProvider::class, 'callLLM');

        try {
            $callLLM->invoke($provider, '<html><body>a page</body></html>', 'https://invalid.invalid/part');
            self::fail('Expected the provider error to be reported');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('LLM invocation failed', $e->getMessage());
            self::assertStringContainsString('Provider returned error', $e->getMessage());
            self::assertInstanceOf(BadRequestException::class, $e->getPrevious());
        }
    }

    public function testTheAnswerOfTheProviderIsPartOfTheMessage(): void
    {
        //"Provider returned error" is what a gateway says when the model provider behind it refused; the reason
        //is in the body it sent along. Without it an administrator cannot tell an exhausted quota from a
        //rejected request, so the status and the body belong in the message.
        $body = '{"error":{"message":"Provider returned error","code":400,"metadata":{"raw":"quota exceeded"}}}';
        $provider = $this->provider($this->platformFailingWithResponse(
            new BadRequestException('Provider returned error'), 400, $body
        ));

        $callLLM = new \ReflectionMethod(AIWebProvider::class, 'callLLM');

        try {
            $callLLM->invoke($provider, '<html><body>a page</body></html>', 'https://invalid.invalid/part');
            self::fail('Expected the provider error to be reported');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('provider answered HTTP 400', $e->getMessage());
            self::assertStringContainsString('quota exceeded', $e->getMessage());
        }
    }
}
