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


namespace App\Tests\Controller;

use App\Entity\UserSystem\User;
use App\Services\AI\AIPlatformRegistry;
use App\Services\AI\AIPlatforms;
use App\Services\InfoProviderSystem\AIPartInfoExtractor;
use App\Services\InfoProviderSystem\DTOJsonSchemaConverter;
use App\Services\InfoProviderSystem\UploadedDocumentStorage;
use App\Settings\InfoProviderSystem\AIExtractorSettings;
use Dompdf\Dompdf;
use Jbtronics\SettingsBundle\Manager\SettingsManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Group("slow")]
#[Group("DB")]
final class InfoProviderFromPdfTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $user = static::getContainer()->get('doctrine')->getManager()->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $this->client->loginUser($user);
    }

    /**
     * Configures the AI extractor and replaces the AI platform with one, which always answers with the given data
     */
    private function configureAI(array $answer): void
    {
        $settings = static::getContainer()->get(AIExtractorSettings::class);
        $settings->platform = AIPlatforms::OPENROUTER;
        $settings->model = 'a/model';

        $converter = new class($answer) implements ResultConverterInterface {
            public function __construct(private readonly array $answer)
            {
            }

            public function supports(Model $model): bool
            {
                return true;
            }

            public function convert(RawResultInterface $result, array $options = []): ResultInterface
            {
                return new ObjectResult($this->answer);
            }

            public function getTokenUsageExtractor(): null
            {
                return null;
            }
        };

        $platform = new class($converter) implements PlatformInterface {
            public function __construct(private readonly ResultConverterInterface $converter)
            {
            }

            public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
            {
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

        static::getContainer()->set(AIPartInfoExtractor::class, new AIPartInfoExtractor($settings,
            new AIPlatformRegistry($settingsManager, [AIPlatforms::OPENROUTER->toServiceTagName() => $platform]),
            new DTOJsonSchemaConverter()));
    }

    private function createPdfUpload(string $html): UploadedFile
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();

        $path = tempnam(sys_get_temp_dir(), 'partdb_test_pdf');
        file_put_contents($path, $dompdf->output());

        return new UploadedFile($path, 'datasheet.pdf', 'application/pdf', null, true);
    }

    private function submitPdf(UploadedFile $file, ?string $context = null): void
    {
        $crawler = $this->client->request('GET', '/en/tools/info_providers/from_pdf');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="from_pdf_form"]')->form();
        $form['from_pdf_form[file]']->upload($file->getPathname());
        if ($context !== null) {
            $form['from_pdf_form[context]'] = $context;
        }
        $this->client->submit($form);
    }

    public function testRedirectsIfAIIsNotConfigured(): void
    {
        $this->client->request('GET', '/en/tools/info_providers/from_pdf');
        self::assertResponseRedirects('/en/tools/info_providers/providers');
    }

    public function testCreatePartFromPdf(): void
    {
        $this->configureAI(['name' => 'BC547', 'description' => 'NPN transistor', 'mpn' => 'BC547B']);

        $this->submitPdf($this->createPdfUpload('<p>BC547 NPN Transistor</p>'));

        self::assertResponseRedirects();
        self::assertStringContainsString('/part/from_info_provider/ai_document/', $this->client->getResponse()->headers->get('Location'));

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertInputValueSame('part_base[name]', 'BC547');
        self::assertInputValueSame('part_base[manufacturer_product_number]', 'BC547B');
    }

    public function testContextIsStoredWithTheDocument(): void
    {
        $this->configureAI(['name' => 'BC547C']);

        $this->submitPdf($this->createPdfUpload('<p>BC547A BC547B BC547C</p>'), 'Use the variant BC547C');

        self::assertResponseRedirects();
        //The provider ID is the token of the stored document, which has to carry the context
        preg_match('#/ai_document/([0-9a-f]+)/create#', $this->client->getResponse()->headers->get('Location'), $matches);
        $document = static::getContainer()->get(UploadedDocumentStorage::class)->retrieve($matches[1]);
        self::assertNotNull($document);
        self::assertSame('Use the variant BC547C', $document->context);
    }

    public function testPdfWithoutText(): void
    {
        $this->configureAI(['name' => 'BC547']);

        $this->submitPdf($this->createPdfUpload('<div style="width: 10px; height: 10px; background: black"></div>'));

        self::assertResponseRedirects('/en/tools/info_providers/from_pdf');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'No text could be extracted from the PDF document');
    }
}
