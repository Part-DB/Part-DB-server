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
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class CrudEndpointTestCase extends AuthenticatedApiTestCase
{
    /**
     * Returns the base path of the endpoint.
     * @return string
     */
    abstract protected function getBasePath(): string;

    protected function getItemPath(int $id): string
    {
        $basePath = $this->getBasePath();
        if (!str_ends_with($basePath, '/')) {
            $basePath .= '/';
        }

        return $basePath . $id;
    }

    /**
     * Returns the id of the created element from the response.
     * @param  ResponseInterface  $response
     * @return int
     */
    protected function getIdOfCreatedElement(ResponseInterface $response): int
    {
        return $response->toArray(true)['id'];
    }

    protected function _testGetCollection(): ResponseInterface
    {
        $response = self::createAuthenticatedClient()->request('GET', $this->getBasePath());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        return $response;
    }

    protected function _testGetChildrenCollection(int $id): ResponseInterface
    {
        $response = self::createAuthenticatedClient()->request('GET', $this->getItemPath($id) . '/children');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        return $response;
    }

    protected function _testGetItem(int $id): ResponseInterface
    {
        $response = self::createAuthenticatedClient()->request('GET', $this->getItemPath($id));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        return $response;
    }

    protected function _testPostItem(array $data): ResponseInterface
    {
        $response = self::createAuthenticatedClient()->request('POST', $this->getBasePath(), ['json' => $data]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        return $response;
    }

    protected function _testPatchItem(int $id, array $data): ResponseInterface
    {
        $response = self::createAuthenticatedClient()->request('PATCH', $this->getItemPath($id), [
            'json' => $data,
            'headers' => ['Content-Type' => 'application/merge-patch+json']
        ]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        return $response;
    }

    protected function _testDeleteItem(int $id): ResponseInterface
    {
        $response = self::createAuthenticatedClient()->request('DELETE', $this->getItemPath($id));
        self::assertResponseIsSuccessful();

        return $response;
    }
}