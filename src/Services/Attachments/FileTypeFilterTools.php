<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\Attachments;

use App\Entity\Attachments\Attachment;
use function in_array;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * A service that helps to work with filetype filters (based on the format <input type=file> accept uses).
 * See https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/file#Unique_file_type_specifiers for
 * more details.
 */
class FileTypeFilterTools
{
    //The file extensions that will be used for the 'video/*', 'image/*', 'audio/*' placeholders
    //These file formats can be directly played in common browesers
    //Source: https://www.chromium.org/audio-video
    protected const IMAGE_EXTS = Attachment::PICTURE_EXTS;
    protected const VIDEO_EXTS = ['mp4', 'ogv', 'ogg', 'webm'];
    protected const AUDIO_EXTS = ['mp3', 'flac', 'ogg', 'oga', 'wav', 'm4a', 'opus'];
    protected const ALLOWED_MIME_PLACEHOLDERS = ['image/*', 'audio/*', 'video/*'];

    public function __construct(protected MimeTypesInterface $mimeTypes, protected CacheInterface $cache)
    {
    }

    /**
     * Check if a filetype filter string is valid.
     *
     * @param string $filter the filter string that should be validated
     *
     * @return bool returns true, if the string is valid
     */
    public function validateFilterString(string $filter): bool
    {
        $filter = trim($filter);
        //An empty filter is valid (means no filter applied)
        if ('' === $filter) {
            return true;
        }

        $elements = explode(',', $filter);
        //Check for each element if it is valid:
        foreach ($elements as $element) {
            $element = trim($element);
            if (!preg_match('#^\.\w+$#', $element) // .ext is allowed
                && !preg_match('#^[-\w.]+/[-\w.]+#', $element) //Explicit MIME type is allowed
                && !in_array($element, static::ALLOWED_MIME_PLACEHOLDERS, false)) { //image/* is allowed
                return false;
            }
        }

        //If no element was invalid, the whole string is valid
        return true;
    }

    /**
     * Normalize a filter string. All extensions are converted to lowercase, too much whitespaces are removed.
     * The filter string is not validated.
     *
     * @param string $filter the filter string that should be normalized
     *
     * @return string The normalized filter string
     */
    public function normalizeFilterString(string $filter): string
    {
        $filter = trim($filter);
        //Replace other separators, with , so we can split it properly
        $filter = str_replace(';', ',', $filter);
        //Make everything lower case
        $filter = strtolower($filter);

        $elements = explode(',', $filter);
        //Check for each element if it is valid:
        foreach ($elements as $key => &$element) {
            $element = trim($element);
            //Remove empty elements
            if ('' === $element) {
                unset($elements[$key]);
            }

            //Convert *.jpg to .jpg
            if (str_starts_with($element, '*.')) {
                $element = str_replace('*.', '.', $element);
            }

            //Convert image to image/*
            if ('image' === $element || 'image/' === $element) {
                $element = 'image/*';
            } elseif ('video' === $element || 'video/' === $element) {
                $element = 'video/*';
            } elseif ('audio' === $element || 'audio/' === $element) {
                $element = 'audio/*';
            } elseif (!preg_match('#^[-\w.]+/[-\w.*]+#', $element) && !str_starts_with($element, '.')) {
                //Convert jpg to .jpg
                $element = '.'.$element;
            }
        }

        $elements = array_unique($elements);

        return implode(',', $elements);
    }

    /**
     * Get a list of all file extensions that matches the given filter string.
     *
     * @param string $filter a valid filetype filter string
     *
     * @return string[] An array of allowed extensions ['txt', 'csv', 'gif']
     */
    public function resolveFileExtensions(string $filter): array
    {
        $filter = trim($filter);

        return $this->cache->get('filter_exts_'.md5($filter), function (ItemInterface $item) use ($filter) {
            $elements = explode(',', $filter);
            $extensions = [];

            foreach ($elements as $element) {
                $element = trim($element);
                if (str_starts_with($element, '.')) {
                    //We found an explicit specified file extension -> add it to list
                    $extensions[] = substr($element, 1);
                } elseif ('image/*' === $element) {
                    $extensions = array_merge($extensions, static::IMAGE_EXTS);
                } elseif ('audio/*' === $element) {
                    $extensions = array_merge($extensions, static::AUDIO_EXTS);
                } elseif ('video/*' === $element) {
                    $extensions = array_merge($extensions, static::VIDEO_EXTS);
                } elseif (preg_match('#^[-\w.]+/[-\w.*]+#', $element)) {
                    $extensions = array_merge($extensions, $this->mimeTypes->getExtensions($element));
                }
            }

            return array_unique($extensions);
        });
    }

    /**
     * Check if the given extension matches the filter.
     *
     * @param string $filter    the filter which should be used for checking
     * @param string $extension the extension that should be checked
     *
     * @return bool returns true, if the extension is allowed with the given filter
     */
    public function isExtensionAllowed(string $filter, string $extension): bool
    {
        $extension = strtolower($extension);

        return empty($filter) || in_array($extension, $this->resolveFileExtensions($filter), false);
    }
}
