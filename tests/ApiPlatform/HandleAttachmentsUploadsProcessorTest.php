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


namespace App\Tests\ApiPlatform;

use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use App\ApiPlatform\HandleAttachmentsUploadsProcessor;
use App\Entity\Attachments\AttachmentUpload;
use App\Entity\Attachments\PartAttachment;
use App\Services\Attachments\AttachmentSubmitHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class HandleAttachmentsUploadsProcessorTest extends TestCase
{
    private function createProcessor(bool $canShowPrivate, bool $expectUpload): HandleAttachmentsUploadsProcessor
    {
        $security = self::createStub(Security::class);
        $security->method('isGranted')->willReturnCallback(
            static fn(string $attribute): bool => $attribute === '@attachments.show_private' && $canShowPrivate
        );

        $submitHandler = $this->createMock(AttachmentSubmitHandler::class);
        $submitHandler->expects($expectUpload ? $this->once() : $this->never())->method('handleUpload');

        $persist = self::createStub(ProcessorInterface::class);
        $persist->method('process')->willReturnArgument(0);

        return new HandleAttachmentsUploadsProcessor($persist, self::createStub(ProcessorInterface::class),
            $submitHandler, $security);
    }

    private function createAttachment(bool $secure, ?bool $requestedPrivate): PartAttachment
    {
        $attachment = new PartAttachment();
        $attachment->setInternalPath(($secure ? '%SECURE%' : '%MEDIA%').'/part/1/file.txt');
        $attachment->setUpload(new AttachmentUpload(file: null, private: $requestedPrivate));

        return $attachment;
    }

    public static function privacyChangeProvider(): \Iterator
    {
        yield 'private to public' => [true, false];
        yield 'public to private' => [false, true];
    }

    #[DataProvider('privacyChangeProvider')]
    public function testChangingPrivacyWithoutPermissionIsDenied(bool $secure, bool $requestedPrivate): void
    {
        $processor = $this->createProcessor(canShowPrivate: false, expectUpload: false);

        $this->expectException(AccessDeniedHttpException::class);
        $processor->process($this->createAttachment($secure, $requestedPrivate), new Patch());
    }

    #[DataProvider('privacyChangeProvider')]
    public function testChangingPrivacyWithPermissionIsAllowed(bool $secure, bool $requestedPrivate): void
    {
        $processor = $this->createProcessor(canShowPrivate: true, expectUpload: true);
        $processor->process($this->createAttachment($secure, $requestedPrivate), new Patch());
    }

    public static function privacyUnchangedProvider(): \Iterator
    {
        yield 'secure, not specified' => [true, null];
        yield 'secure, stays secure' => [true, true];
        yield 'public, not specified' => [false, null];
        yield 'public, stays public' => [false, false];
    }

    #[DataProvider('privacyUnchangedProvider')]
    public function testUploadWithoutPrivacyChangeNeedsNoPermission(bool $secure, ?bool $requestedPrivate): void
    {
        $processor = $this->createProcessor(canShowPrivate: false, expectUpload: true);
        $processor->process($this->createAttachment($secure, $requestedPrivate), new Patch());
    }
}
