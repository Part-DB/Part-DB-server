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


namespace App\Services\InfoProviderSystem;

use App\Services\InfoProviderSystem\DTOs\UploadedDocument;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Temporarily stores the text of documents uploaded to create parts from, until an info provider has processed them.
 */
class UploadedDocumentStorage
{
    private const CACHE_KEY_PREFIX = 'uploaded_document_';
    private const CACHE_TTL = 7200; // 2 hours

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Stores the given document in the cache.
     * @return string The token under which the document was stored. It is the same value as $document->token.
     */
    public function store(UploadedDocument $document): string
    {
        $item = $this->cache->getItem(self::CACHE_KEY_PREFIX . $document->token);
        $item->set($document);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        return $document->token;
    }

    /**
     * Retrieves the stored document via its token. Returns null if not found or expired.
     */
    public function retrieve(string $token): ?UploadedDocument
    {
        //Tokens are xxh3 hashes, everything else can not be a valid token (and might not be a valid cache key)
        if (!preg_match('/^[0-9a-f]{16}$/', $token)) {
            return null;
        }

        $item = $this->cache->getItem(self::CACHE_KEY_PREFIX . $token);
        if (!$item->isHit()) {
            return null;
        }
        return $item->get();
    }
}
