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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Temporarily stores documents uploaded to create parts from, until an info provider has processed them and the part
 * is created.
 *
 * The document (with its extracted text) is held in the cache. The original file is stored in a directory on disk
 * instead, as it can be big: a cache is not meant for that (APCu or Memcached can not even hold big items), and the
 * filesystem cache only deletes expired entries when they are read again, which never happens for an abandoned upload.
 * Expired files are deleted from that directory whenever a new document is stored.
 */
class UploadedDocumentStorage
{
    private const CACHE_KEY_PREFIX = 'uploaded_document_';
    private const TTL = 7200; // 2 hours

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        #[Autowire('%kernel.share_dir%/uploaded_documents')]
        private readonly string $fileDirectory,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Stores the given document.
     * @param  UploadedDocument  $document  The document (its extracted text)
     * @param  File|null  $file  The original file, so that it can be attached to the created part later (see
     * retrieveFilePath()). It is moved into the storage. Its size has to be given in $document->fileSize.
     * @return string The token under which the document was stored. It is the same value as $document->token.
     */
    public function store(UploadedDocument $document, ?File $file = null): string
    {
        if ($file !== null && $file->getSize() !== $document->fileSize) {
            throw new \InvalidArgumentException('The file size of the document does not match the given file.');
        }

        $this->deleteExpiredFiles();

        if ($file !== null) {
            $file->move($this->fileDirectory, $document->token);
            //The modification time determines, when the file expires
            $this->filesystem->touch($this->getFilePath($document->token));
        }

        $item = $this->cache->getItem(self::CACHE_KEY_PREFIX . $document->token);
        $item->set($document);
        $item->expiresAfter(self::TTL);
        $this->cache->save($item);

        return $document->token;
    }

    /**
     * Retrieves the stored document via its token. Returns null if not found or expired.
     */
    public function retrieve(string $token): ?UploadedDocument
    {
        if (!$this->isValidToken($token)) {
            return null;
        }

        $item = $this->cache->getItem(self::CACHE_KEY_PREFIX . $token);
        if (!$item->isHit()) {
            return null;
        }
        return $item->get();
    }

    /**
     * Returns the path of the original file of the document with the given token. The file must not be modified or
     * moved, copy it instead.
     * @return string|null The path, or null if the document was stored without its file, or the file has expired.
     */
    public function retrieveFilePath(string $token): ?string
    {
        if (!$this->isValidToken($token)) {
            return null;
        }

        $path = $this->getFilePath($token);
        clearstatcache(true, $path);
        if (!is_file($path) || $this->isExpired(filemtime($path))) {
            return null;
        }

        return $path;
    }

    private function deleteExpiredFiles(): void
    {
        if (!is_dir($this->fileDirectory)) {
            return;
        }

        $expired = Finder::create()->files()->in($this->fileDirectory)->depth(0)->ignoreDotFiles(false)
            ->filter(fn(SplFileInfo $file): bool => $this->isExpired($file->getMTime()));

        $this->filesystem->remove($expired);
    }

    private function isExpired(int|false $modificationTime): bool
    {
        return $modificationTime === false || $modificationTime < time() - self::TTL;
    }

    private function getFilePath(string $token): string
    {
        return $this->fileDirectory . DIRECTORY_SEPARATOR . $token;
    }

    /**
     * Tokens are xxh3 hashes, everything else can not be a valid token. This also ensures, that a token can be used
     * as cache key and filename (no path traversal).
     */
    private function isValidToken(string $token): bool
    {
        return preg_match('/^[0-9a-f]{16}$/', $token) === 1;
    }
}
