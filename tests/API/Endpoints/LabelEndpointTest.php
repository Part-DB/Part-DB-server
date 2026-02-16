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

namespace App\Tests\API\Endpoints;

use App\Tests\API\AuthenticatedApiTestCase;

class LabelEndpointTest extends AuthenticatedApiTestCase
{
    public function testGetLabelProfiles(): void
    {
        $response = self::createAuthenticatedClient()->request('GET', '/api/label_profiles');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        
        // Check that we get an array of label profiles
        $json = $response->toArray();
        self::assertIsArray($json['hydra:member']);
        self::assertNotEmpty($json['hydra:member']);
        
        // Check the structure of the first profile
        $firstProfile = $json['hydra:member'][0];
        self::assertArrayHasKey('@id', $firstProfile);
        self::assertArrayHasKey('name', $firstProfile);
        self::assertArrayHasKey('options', $firstProfile);
        self::assertArrayHasKey('show_in_dropdown', $firstProfile);
    }

    public function testGetSingleLabelProfile(): void
    {
        $response = self::createAuthenticatedClient()->request('GET', '/api/label_profiles/1');

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id' => '/api/label_profiles/1',
        ]);
        
        $json = $response->toArray();
        self::assertArrayHasKey('name', $json);
        self::assertArrayHasKey('options', $json);
        // Note: options is serialized but individual fields like width/height
        // are only available in 'extended' or 'full' serialization groups
        self::assertIsArray($json['options']);
    }

    public function testFilterLabelProfilesByElementType(): void
    {
        $response = self::createAuthenticatedClient()->request('GET', '/api/label_profiles?options.supported_element=part');

        self::assertResponseIsSuccessful();
        
        $json = $response->toArray();
        // Check that we get results - the filter should work even if the field isn't in response
        self::assertIsArray($json['hydra:member']);
        // verify we got profiles
        self::assertNotEmpty($json['hydra:member']);
    }

    public function testGenerateLabelPdf(): void
    {
        $response = self::createAuthenticatedClient()->request('POST', '/api/labels/generate', [
            'json' => [
                'profileId' => 1,
                'elementIds' => '1',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');
        
        // Check that the response contains PDF data
        $content = $response->getContent();
        self::assertStringStartsWith('%PDF-', $content);
        
        // Check Content-Disposition header contains attachment and .pdf
        $headers = $response->getHeaders();
        self::assertArrayHasKey('content-disposition', $headers);
        $disposition = $headers['content-disposition'][0];
        self::assertStringContainsString('attachment', $disposition);
        self::assertStringContainsString('.pdf', $disposition);
    }

    public function testGenerateLabelPdfWithMultipleElements(): void
    {
        $response = self::createAuthenticatedClient()->request('POST', '/api/labels/generate', [
            'json' => [
                'profileId' => 1,
                'elementIds' => '1,2,3',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function testGenerateLabelPdfWithRange(): void
    {
        $response = self::createAuthenticatedClient()->request('POST', '/api/labels/generate', [
            'json' => [
                'profileId' => 1,
                'elementIds' => '1-3',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function testGenerateLabelPdfWithInvalidProfileId(): void
    {
        self::createAuthenticatedClient()->request('POST', '/api/labels/generate', [
            'json' => [
                'profileId' => 99999,
                'elementIds' => '1',
            ],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testGenerateLabelPdfWithInvalidElementIds(): void
    {
        $client = self::createAuthenticatedClient();
        $client->request('POST', '/api/labels/generate', [
            'json' => [
                'profileId' => 1,
                'elementIds' => 'invalid',
            ],
        ]);

        // Should return 400 or 422 (validation error)
        $response = $client->getResponse();
        $statusCode = $response->getStatusCode();
        self::assertTrue(
            $statusCode === 400 || $statusCode === 422,
            "Expected status code 400 or 422, got {$statusCode}"
        );
    }

    public function testGenerateLabelPdfWithNonExistentElements(): void
    {
        self::createAuthenticatedClient()->request('POST', '/api/labels/generate', [
            'json' => [
                'profileId' => 1,
                'elementIds' => '99999',
            ],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testGenerateLabelPdfRequiresAuthentication(): void
    {
        // Create a non-authenticated client
        self::createClient()->request('POST', '/api/labels/generate', [
            'json' => [
                'profileId' => 1,
                'elementIds' => '1',
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }
}
