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

namespace App\Tests\Services\AI\Contract;

use App\Services\AI\Contract\AIPlatformContractFactory;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Generic\CompletionsModel;
use Symfony\AI\Platform\Message\Content\Document;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

final class AIPlatformContractFactoryTest extends TestCase
{
    private function createInput(object $content): MessageBag
    {
        return new MessageBag(
            Message::forSystem('System prompt'),
            Message::ofUser('Extract this', $content),
        );
    }

    public function testOpenAICompatibleDocument(): void
    {
        $payload = AIPlatformContractFactory::createOpenAICompatible()->createRequestPayload(new CompletionsModel('a/model'),
            $this->createInput(new Document('%PDF-1.4', 'application/pdf', '/tmp/0123456789abcdef')));

        self::assertSame([
            ['type' => 'text', 'text' => 'Extract this'],
            ['type' => 'file', 'file' => [
                //The stored files have no extension, but the APIs need one
                'filename' => '0123456789abcdef.pdf',
                'file_data' => 'data:application/pdf;base64,'.base64_encode('%PDF-1.4'),
            ]],
        ], $payload['messages'][1]['content']);
    }

    public function testOpenAICompatibleDocumentKeepsExtension(): void
    {
        $payload = AIPlatformContractFactory::createOpenAICompatible()->createRequestPayload(new CompletionsModel('a/model'),
            $this->createInput(new Document('%PDF-1.4', 'application/pdf', '/tmp/datasheet.pdf')));

        self::assertSame('datasheet.pdf', $payload['messages'][1]['content'][1]['file']['filename']);
    }

    public function testOpenAICompatibleImage(): void
    {
        $payload = AIPlatformContractFactory::createOpenAICompatible()->createRequestPayload(new CompletionsModel('a/model'),
            $this->createInput(new Image('PNG', 'image/png')));

        self::assertSame(['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.base64_encode('PNG')]],
            $payload['messages'][1]['content'][1]);
    }

    public function testOllamaImage(): void
    {
        $payload = AIPlatformContractFactory::createOllama()->createRequestPayload(new CompletionsModel('a/model'),
            new MessageBag(
                Message::forSystem('System prompt'),
                Message::ofUser('Extract this', new Image('PNG', 'image/png'), 'Second text'),
                Message::ofUser('Text only'),
            ));

        self::assertSame(['role' => 'system', 'content' => 'System prompt'], $payload['messages'][0]);
        self::assertSame([
            'role' => 'user',
            'content' => "Extract this\n\nSecond text",
            'images' => [base64_encode('PNG')],
        ], $payload['messages'][1]);
        //Messages without images are left to the default normalizer
        self::assertSame(['role' => 'user', 'content' => 'Text only'], $payload['messages'][2]);
    }

    public function testOllamaRejectsDocuments(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ollama does not support Document content');

        AIPlatformContractFactory::createOllama()->createRequestPayload(new CompletionsModel('a/model'),
            $this->createInput(new Document('%PDF-1.4', 'application/pdf')));
    }
}
