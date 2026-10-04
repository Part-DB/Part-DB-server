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

use App\Entity\Attachments\Attachment;
use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use App\Services\AI\AIPlatformRegistry;
use App\Services\Attachments\AttachmentPathResolver;
use App\Services\AI\AIPlatforms;
use App\Services\InfoProviderSystem\AIPartInfoExtractor;
use App\Services\InfoProviderSystem\DTOJsonSchemaConverter;
use App\Services\InfoProviderSystem\UploadedDocumentStorage;
use App\Settings\InfoProviderSystem\AIWebExtractorSettings;
use App\Settings\InfoProviderSystem\AIFileExtractorSettings;
use App\Services\InfoProviderSystem\AIFileInputMode;
use Dompdf\Dompdf;
use Jbtronics\SettingsBundle\Manager\SettingsManagerInterface;
use PHPUnit\Framework\Attributes\Group;
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
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Group("slow")]
#[Group("DB")]
final class InfoProviderFromFileTest extends WebTestCase
{
    private KernelBrowser $client;

    /** @var MessageBag[] The inputs the fake AI platform was invoked with */
    private array $invocations = [];

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
        $settings = static::getContainer()->get(AIFileExtractorSettings::class);
        $settings->platform = AIPlatforms::OPENROUTER;
        $settings->model = 'a/model';
        //Disabled by default, the tests of the file input modes need it
        $settings->allowFileInput = true;

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

        static::getContainer()->set(AIPartInfoExtractor::class, new AIPartInfoExtractor(
            new AIPlatformRegistry($settingsManager, [AIPlatforms::OPENROUTER->toServiceTagName() => $platform]),
            new DTOJsonSchemaConverter()));
    }

    private function createPdfUpload(string $html): UploadedFile
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->render();

        //The browser sends the name of the file on disk as the original filename
        $dir = sys_get_temp_dir().'/partdb_test_'.bin2hex(random_bytes(8));
        mkdir($dir);
        $path = $dir.'/datasheet.pdf';
        file_put_contents($path, $dompdf->output());

        return new UploadedFile($path, 'datasheet.pdf', 'application/pdf', null, true);
    }

    private function disallowFileInput(): void
    {
        static::getContainer()->get(AIFileExtractorSettings::class)->allowFileInput = false;
    }

    private function createImageUpload(): UploadedFile
    {
        //A 1x1 pixel PNG. Followed by random bytes, as the document is identified by its content, and the extraction
        //result is cached across tests
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==')
            .random_bytes(8);

        $dir = sys_get_temp_dir().'/partdb_test_'.bin2hex(random_bytes(8));
        mkdir($dir);
        $path = $dir.'/photo.png';
        file_put_contents($path, $png);

        return new UploadedFile($path, 'photo.png', 'image/png', null, true);
    }

    /**
     * @return ContentInterface[] The content of the message containing the document, sent in the last invocation
     */
    private function getSentContent(): array
    {
        self::assertNotEmpty($this->invocations, 'The AI model was not invoked');
        return end($this->invocations)->getUserMessage()?->getContent() ?? [];
    }

    private function submitFile(UploadedFile $file, ?string $context = null, ?AIFileInputMode $inputMode = null): void
    {
        $crawler = $this->client->request('GET', '/en/tools/info_providers/from_file');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[name="from_file_form"]')->form();
        $form['from_file_form[file]']->upload($file->getPathname());
        if ($context !== null) {
            $form['from_file_form[context]'] = $context;
        }
        if ($inputMode !== null) {
            $form['from_file_form[input_mode]'] = $inputMode->value;
        }
        $this->client->submit($form);
    }

    public function testRedirectsIfAIIsNotConfigured(): void
    {
        $this->client->request('GET', '/en/tools/info_providers/from_file');
        self::assertResponseRedirects('/en/tools/info_providers/providers');
    }

    public function testWebExtractorSettingsDoNotEnableThePage(): void
    {
        //The file extractor has its own settings, configuring the web extractor must not be enough
        $settings = static::getContainer()->get(AIWebExtractorSettings::class);
        $settings->platform = AIPlatforms::OPENROUTER;
        $settings->model = 'a/model';

        $this->client->request('GET', '/en/tools/info_providers/from_file');
        self::assertResponseRedirects('/en/tools/info_providers/providers');
    }

    public function testSettingsPagesRender(): void
    {
        //The settings of the provider page are the separate AI File Extractor settings
        $crawler = $this->client->request('GET', '/en/tools/info_providers/provider/ai_document/settings');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('[data-platform-selector-label="ai_file_extractor"]')->count());

        //Both AI extractors are part of the system settings, each with its own platform selector for its model field
        $crawler = $this->client->request('GET', '/en/settings');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('[data-platform-selector-label="ai_extractor"]')->count());
        self::assertSame(1, $crawler->filter('[data-platform-selector-label="ai_file_extractor"]')->count());
    }

    public function testCreatePartFromFile(): void
    {
        $this->configureAI(['name' => 'BC547', 'description' => 'NPN transistor', 'mpn' => 'BC547B']);

        $this->submitFile($this->createPdfUpload('<p>BC547 NPN Transistor</p>'));

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

        $this->submitFile($this->createPdfUpload('<p>BC547A BC547B BC547C</p>'), 'Use the variant BC547C');

        self::assertResponseRedirects();
        //The provider ID is the token of the stored document, which has to carry the context
        preg_match('#/ai_document/([0-9a-f]+)/create#', $this->client->getResponse()->headers->get('Location'), $matches);
        $document = static::getContainer()->get(UploadedDocumentStorage::class)->retrieve($matches[1]);
        self::assertNotNull($document);
        self::assertSame('Use the variant BC547C', $document->context);
    }

    private function createScannedPdfUpload(): UploadedFile
    {
        //No text, but a unique image, as the document is identified by its content, and the extraction result is cached across tests
        $color = substr(bin2hex(random_bytes(3)), 0, 6);
        return $this->createPdfUpload('<div style="width: 10px; height: 10px; background: #'.$color.'"></div>');
    }

    public function testPdfWithoutTextInTextMode(): void
    {
        $this->configureAI(['name' => 'BC547']);

        $this->submitFile($this->createScannedPdfUpload(), inputMode: AIFileInputMode::TEXT);

        self::assertResponseRedirects('/en/tools/info_providers/from_file');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'No text could be extracted from the file');
        self::assertSame([], $this->invocations);
    }

    public function testPdfWithoutTextIsSentToTheModelInAutoMode(): void
    {
        $this->configureAI(['name' => 'BC547 scanned']);
        $upload = $this->createScannedPdfUpload();
        $pdfContent = file_get_contents($upload->getPathname());

        $this->submitFile($upload);

        self::assertResponseRedirects();
        self::assertStringContainsString('/part/from_info_provider/ai_document/', $this->client->getResponse()->headers->get('Location'));
        $content = $this->getSentContent();
        self::assertInstanceOf(Document::class, $content[1] ?? null);
        self::assertSame($pdfContent, $content[1]->asBinary());

        $this->client->followRedirect();
        self::assertInputValueSame('part_base[name]', 'BC547 scanned');
    }

    public function testPdfWithTextIsSentAsTextInAutoMode(): void
    {
        $this->configureAI(['name' => 'BC547']);

        $this->submitFile($this->createPdfUpload('<p>BC547 auto mode '.bin2hex(random_bytes(8)).'</p>'));

        self::assertResponseRedirects();
        $content = $this->getSentContent();
        self::assertCount(1, $content);
        self::assertInstanceOf(Text::class, $content[0]);
        self::assertStringContainsString('BC547 auto mode', $content[0]->getText());
    }

    public function testPdfWithTextIsSentAsFileInFileMode(): void
    {
        $this->configureAI(['name' => 'BC547']);

        $this->submitFile($this->createPdfUpload('<p>BC547 file mode '.bin2hex(random_bytes(8)).'</p>'), inputMode: AIFileInputMode::FILE);

        self::assertResponseRedirects();
        self::assertInstanceOf(Document::class, $this->getSentContent()[1] ?? null);
    }

    public function testImageIsSentToTheModel(): void
    {
        $this->configureAI(['name' => 'BC547 photo']);
        $upload = $this->createImageUpload();
        $imageContent = file_get_contents($upload->getPathname());

        $this->submitFile($upload);

        self::assertResponseRedirects();
        $content = $this->getSentContent();
        self::assertInstanceOf(Image::class, $content[1] ?? null);
        self::assertSame('image/png', $content[1]->getFormat());
        self::assertSame($imageContent, $content[1]->asBinary());

        //The image is attached to the part like any other file
        $this->client->followRedirect();
        self::assertInputValueSame('part_base[name]', 'BC547 photo');
        self::assertInputValueSame('part_base[attachments][0][name]', 'photo.png');
    }

    public function testImagesAreRejectedInTextMode(): void
    {
        $this->configureAI(['name' => 'BC547']);

        $this->submitFile($this->createImageUpload(), inputMode: AIFileInputMode::TEXT);

        self::assertResponseRedirects('/en/tools/info_providers/from_file');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Images contain no text to extract');
        self::assertSame([], $this->invocations);
    }

    public function testInputModeIsOnlyOfferedIfFileInputIsAllowed(): void
    {
        $this->configureAI(['name' => 'BC547']);

        $crawler = $this->client->request('GET', '/en/tools/info_providers/from_file');
        self::assertSame(count(AIFileInputMode::cases()), $crawler->filter('input[type="radio"][name="from_file_form[input_mode]"]')->count());
        self::assertStringContainsString('.png', $crawler->filter('input[name="from_file_form[file]"]')->attr('accept'));

        $this->disallowFileInput();
        $crawler = $this->client->request('GET', '/en/tools/info_providers/from_file');
        self::assertSame(0, $crawler->filter('input[type="radio"][name="from_file_form[input_mode]"]')->count());
        self::assertStringNotContainsString('.png', $crawler->filter('input[name="from_file_form[file]"]')->attr('accept'));
    }

    public function testImagesAreRejectedIfFileInputIsNotAllowed(): void
    {
        $this->configureAI(['name' => 'BC547']);
        $this->disallowFileInput();

        $this->submitFile($this->createImageUpload());

        //The form is shown again with the validation error
        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->invocations);
    }

    public function testPdfWithoutTextIsNotSentIfFileInputIsNotAllowed(): void
    {
        $this->configureAI(['name' => 'BC547']);
        $this->disallowFileInput();

        $this->submitFile($this->createScannedPdfUpload());

        self::assertResponseRedirects('/en/tools/info_providers/from_file');
        self::assertSame([], $this->invocations);
    }

    /**
     * Uploads a PDF and opens the part creation form, which the upload redirects to.
     * @return array{0: Form, 1: string} The part form, and the content of the uploaded PDF
     */
    private function openPartFormForUploadedPdf(): array
    {
        $this->configureAI(['name' => 'BC547 attachment test', 'description' => 'NPN transistor']);

        //Unique text, as the document is identified by its content, and the extraction result is cached across tests
        $upload = $this->createPdfUpload('<p>BC547 NPN Transistor '.bin2hex(random_bytes(8)).'</p>');
        $pdfContent = file_get_contents($upload->getPathname());
        $this->submitFile($upload);
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        //Created via the save button, so that it counts as clicked on submit
        $form = $crawler->filter('button[name="part_base[save]"]')->form();
        $form->disableValidation();
        //A category is required to save a part
        $form['part_base[category]'] = '1';

        return [$form, $pdfContent];
    }

    public function testUploadedFileIsAttachedToTheCreatedPart(): void
    {
        [$form, $pdfContent] = $this->openPartFormForUploadedPdf();

        //The attachment is shown in the form, with a hint that the file is stored on saving
        self::assertSame('datasheet.pdf', $form['part_base[attachments][0][name]']->getValue());
        self::assertSelectorTextContains('body', 'This file will be attached when the part is saved');

        $this->client->submit($form);
        self::assertResponseRedirects();
        preg_match('#/part/(\d+)/edit#', $this->client->getResponse()->headers->get('Location'), $matches);

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $part = $em->find(Part::class, (int) $matches[1]);
        self::assertSame('BC547 attachment test', $part->getName());
        self::assertCount(1, $part->getAttachments());

        /** @var Attachment $attachment */
        $attachment = $part->getAttachments()->first();
        $path = static::getContainer()->get(AttachmentPathResolver::class)->placeholderToRealPath($attachment->getInternalPath());
        try {
            self::assertSame('datasheet.pdf', $attachment->getName());
            self::assertSame('datasheet.pdf', $attachment->getFilename());
            self::assertSame('Datasheet', $attachment->getAttachmentType()?->getName());
            self::assertFalse($attachment->hasExternal());
            //The stored file is the uploaded one
            self::assertNotNull($path);
            self::assertFileExists($path);
            self::assertSame($pdfContent, file_get_contents($path));
        } finally {
            //The database is rolled back after the test, but the stored file is not
            if ($path !== null && is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testRemovedAttachmentIsNotStored(): void
    {
        [$form] = $this->openPartFormForUploadedPdf();

        //The user can remove the attachment in the form like any other one
        foreach (array_keys($form->all()) as $name) {
            if (str_starts_with($name, 'part_base[attachments][0]')) {
                $form->remove($name);
            }
        }

        $this->client->submit($form);
        self::assertResponseRedirects();
        preg_match('#/part/(\d+)/edit#', $this->client->getResponse()->headers->get('Location'), $matches);

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();
        self::assertCount(0, $em->find(Part::class, (int) $matches[1])->getAttachments());
    }

    public function testExpiredFileIsNotAttached(): void
    {
        [$form] = $this->openPartFormForUploadedPdf();

        //The file expires while the form is open (the document itself is still known)
        preg_match('#/ai_document/([0-9a-f]+)/create#', $this->client->getRequest()->getUri(), $matches);
        unlink(static::getContainer()->get(UploadedDocumentStorage::class)->retrieveFilePath($matches[1]));

        $this->client->submit($form);
        self::assertResponseRedirects();
        preg_match('#/part/(\d+)/edit#', $this->client->getResponse()->headers->get('Location'), $partMatches);

        //The part is still created, without an empty attachment, and the user is told why the file is missing
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();
        self::assertCount(0, $em->find(Part::class, (int) $partMatches[1])->getAttachments());

        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'The uploaded file is not available anymore');
    }
}
