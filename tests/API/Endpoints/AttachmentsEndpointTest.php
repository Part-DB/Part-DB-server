<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Tests\API\Endpoints;

use App\Tests\API\AuthenticatedApiTestCase;

class AttachmentsEndpointTest extends AuthenticatedApiTestCase
{
    public function testGetCollection(): void
    {
        $response = static::createAuthenticatedClient()->request('GET', '/api/attachments');
        self::assertResponseIsSuccessful();
        //There should be 2 attachments in the database yet
        self::assertJsonContains([
            '@context' => '/api/contexts/Attachment',
            '@id' => '/api/attachments',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 2,
        ]);
    }

    public function testCreateAttachmentGuessTypeFromElement(): void
    {
        $response = static::createAuthenticatedClient()->request('POST', '/api/attachments', ['json' => [
            'name' => 'test',
            'element' => '/api/parts/1',
            'attachment_type' => '/api/attachment_types/1'
        ]]);

        //The attachment should be created successfully
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'name' => 'test',
        ]);
    }

    public function testCreateAttachmentDiscriminatorColumn(): void
    {
        $response = static::createAuthenticatedClient()->request('POST', '/api/attachments', ['json' => [
            'name' => 'test',
            'element' => '/api/parts/1',
            'attachment_type' => '/api/attachment_types/1',
            '_type' => "Part",
        ]]);

        //The attachment should be created successfully
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'name' => 'test',
        ]);
    }

    public function testUploadFile(): void
    {
        $response = static::createAuthenticatedClient()->request('POST', '/api/attachments', ['json' => [
            'name' => 'test',
            'element' => '/api/parts/1',
            'attachment_type' => '/api/attachment_types/1',
            '_type' => "Part",
            "upload" => [
                "data" => "data:@file/octet-stream;base64,LS0gcGhwTXlB",
                "filename" => "test.csv",
                "private" => true
            ],
        ]]);

        //The attachment should be created successfully
        self::assertResponseIsSuccessful();

        //Attachment must be set (not null)
        $array = json_decode($response->getContent(), true);

        self::assertNotNull($array['internal_path']);

        //Attachment must be private
        self::assertJsonContains([
            'private' => true,
        ]);
    }

    public function testRemoveAttachment(): void
    {
        $response = static::createAuthenticatedClient()->request('DELETE', '/api/attachments/1');
        self::assertResponseIsSuccessful();
    }
}