<?php

declare(strict_types=1);

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
namespace App\Tests\Serializer\APIPlatform;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Serializer\ItemNormalizer;
use App\Entity\Attachments\Attachment;
use App\Serializer\APIPlatform\SkippableItemNormalizer;
use PHPUnit\Framework\TestCase;

final class SkippableItemNormalizerTest extends TestCase
{
    private ItemNormalizer&\PHPUnit\Framework\MockObject\MockObject $inner;
    private IriConverterInterface&\PHPUnit\Framework\MockObject\MockObject $iriConverter;
    private ResourceClassResolverInterface&\PHPUnit\Framework\MockObject\MockObject $resourceClassResolver;
    private SkippableItemNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->inner = $this->createMock(ItemNormalizer::class);
        $this->iriConverter = $this->createMock(IriConverterInterface::class);
        $this->resourceClassResolver = $this->createMock(ResourceClassResolverInterface::class);
        $this->normalizer = new SkippableItemNormalizer($this->inner, $this->iriConverter, $this->resourceClassResolver);
    }

    public function testStringIsResolvedAsIriForResourceClass(): void
    {
        //Regression guard for https://github.com/Part-DB/Part-DB-server/issues/1370: an IRI string for a resource
        //class (like Attachment, which has a discriminator map) must still be resolved via the IriConverter.
        $this->resourceClassResolver->method('isResourceClass')->with(Attachment::class)->willReturn(true);

        $attachment = $this->createMock(Attachment::class);
        $this->iriConverter->expects($this->once())
            ->method('getResourceFromIri')
            ->with('/api/attachments/1')
            ->willReturn($attachment);

        $result = $this->normalizer->denormalize('/api/attachments/1', Attachment::class);

        $this->assertSame($attachment, $result);
    }

    public function testStringIsNotResolvedAsIriForNonResourceClass(): void
    {
        //A backed enum (or any other plain value object) is not an API resource, so a string value denormalized
        //into it is the enum's scalar value, not an IRI - it must be passed through to the inner normalizer
        //(which delegates to Symfony's BackedEnumNormalizer) instead of being swallowed by a failed IRI lookup.
        $this->resourceClassResolver->method('isResourceClass')->willReturn(false);

        $this->iriConverter->expects($this->never())->method('getResourceFromIri');
        $this->inner->expects($this->once())
            ->method('denormalize')
            ->with('warning', 'SomeEnum', null, [])
            ->willReturn('warning-denormalized');

        $result = $this->normalizer->denormalize('warning', 'SomeEnum');

        $this->assertSame('warning-denormalized', $result);
    }

    public function testArrayWithIriIsResolvedForResourceClass(): void
    {
        $this->resourceClassResolver->method('isResourceClass')->with(Attachment::class)->willReturn(true);

        $attachment = $this->createMock(Attachment::class);
        $this->iriConverter->expects($this->once())
            ->method('getResourceFromIri')
            ->with('/api/attachments/1')
            ->willReturn($attachment);

        $result = $this->normalizer->denormalize(['@id' => '/api/attachments/1'], Attachment::class);

        $this->assertSame($attachment, $result);
    }

    public function testArrayWithoutIriIsPassedThrough(): void
    {
        $this->resourceClassResolver->method('isResourceClass')->willReturn(true);

        $this->iriConverter->expects($this->never())->method('getResourceFromIri');
        $this->inner->expects($this->once())
            ->method('denormalize')
            ->with(['name' => 'Test'], Attachment::class, null, [])
            ->willReturn('denormalized');

        $result = $this->normalizer->denormalize(['name' => 'Test'], Attachment::class);

        $this->assertSame('denormalized', $result);
    }
}
