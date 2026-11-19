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


namespace App\Tests\Services\Attachments;

use App\Entity\Attachments\AttachmentUpload;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Part;
use App\Services\Attachments\AttachmentPathResolver;
use App\Services\Attachments\AttachmentSubmitHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Tests that uploaded files never end up on disk with a dangerous extension.
 */
final class AttachmentSubmitHandlerExtensionTest extends KernelTestCase
{
    private ?string $storedFile = null;

    protected function tearDown(): void
    {
        if ($this->storedFile !== null) {
            //Remove the whole folder of the fake part, so no leftovers stay in the media folder
            (new Filesystem())->remove(dirname($this->storedFile));
        }
        parent::tearDown();
    }

    public static function extensionProvider(): \Generator
    {
        yield 'plain php' => ['shell.php', 'txt'];
        yield 'uppercase php' => ['shell.PHP', 'txt'];
        //These extensions are not blacklisted as given, but become dangerous after normalization
        yield 'dash inside' => ['shell.p-hp', 'txt'];
        yield 'trailing space' => ['shell.php ', 'txt'];
        yield 'fullwidth chars' => ['shell.ｐｈｐ', 'txt'];
        yield 'phar' => ['shell.phar', 'txt'];
        yield 'safe extension' => ['datasheet.pdf', 'pdf'];
        yield 'safe uppercase extension' => ['image.JPG', 'jpg'];
    }

    #[DataProvider('extensionProvider')]
    public function testGetSafeExtension(string $originalName, string $expectedExtension): void
    {
        //Check the extension decision directly, as handleUpload() would also rename dangerous files afterward,
        //but the file must never land on disk with a dangerous extension in the first place
        self::bootKernel();
        $handler = static::getContainer()->get(AttachmentSubmitHandler::class);

        $method = new \ReflectionMethod(AttachmentSubmitHandler::class, 'getSafeExtension');
        self::assertSame($expectedExtension, $method->invoke($handler, pathinfo($originalName, PATHINFO_EXTENSION)));
    }

    #[DataProvider('extensionProvider')]
    public function testUploadedFileGetsSafeExtension(string $originalName, string $expectedExtension): void
    {
        self::bootKernel();
        $handler = static::getContainer()->get(AttachmentSubmitHandler::class);
        $pathResolver = static::getContainer()->get(AttachmentPathResolver::class);

        $part = new Part();
        (new \ReflectionProperty(Part::class, 'id'))->setValue($part, 999999);
        $attachment = new PartAttachment();
        $attachment->setName('test');
        $part->addAttachment($attachment);

        $tmpFile = tempnam(sys_get_temp_dir(), 'partdb_test');
        file_put_contents($tmpFile, '<?php echo "pwned";');
        $file = new UploadedFile($tmpFile, $originalName, null, null, true);

        $handler->handleUpload($attachment, new AttachmentUpload(file: $file));

        $this->storedFile = $pathResolver->placeholderToRealPath($attachment->getInternalPath());
        self::assertFileExists($this->storedFile);
        self::assertSame($expectedExtension, pathinfo($this->storedFile, PATHINFO_EXTENSION));
        //The original filename is kept for display/download purposes
        self::assertSame($originalName, $attachment->getFilename());
    }
}
