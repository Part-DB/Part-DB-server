<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Services\Attachments;

use Symfony\Component\Filesystem\Filesystem;

/**
 * This service converts the relative pathes for attachments saved in database (like %MEDIA%/img.jpg) to real pathes
 * an vice versa.
 */
class AttachmentPathResolver
{
    protected $project_dir;

    protected $media_path;
    protected $footprints_path;
    protected $models_path;
    protected $secure_path;

    protected $placeholders;
    protected $pathes;
    protected $placeholders_regex;
    protected $pathes_regex;

    /**
     * AttachmentPathResolver constructor.
     *
     * @param string      $project_dir     the kernel that should be used to resolve the project dir
     * @param string      $media_path      the path where uploaded attachments should be stored
     * @param string|null $footprints_path The path where builtin attachments are stored.
     *                                     Set to null if this ressource should be disabled.
     * @param string|null $models_path     set to null if this ressource should be disabled
     */
    public function __construct(string $project_dir, string $media_path, string $secure_path, ?string $footprints_path, ?string $models_path)
    {
        $this->project_dir = $project_dir;

        //Determine the path for our ressources
        $this->media_path = $this->parameterToAbsolutePath($media_path);
        $this->footprints_path = $this->parameterToAbsolutePath($footprints_path);
        $this->models_path = $this->parameterToAbsolutePath($models_path);
        $this->secure_path = $this->parameterToAbsolutePath($secure_path);

        //Here we define the valid placeholders and their replacement values
        $this->placeholders = ['%MEDIA%', '%BASE%/data/media', '%FOOTPRINTS%', '%FOOTPRINTS_3D%', '%SECURE%'];
        $this->pathes = [$this->media_path, $this->media_path, $this->footprints_path, $this->models_path, $this->secure_path];

        //Remove all disabled placeholders
        foreach ($this->pathes as $key => $path) {
            if (null === $path) {
                unset($this->placeholders[$key], $this->pathes[$key]);
            }
        }

        //Create the regex arrays
        $this->placeholders_regex = $this->arrayToRegexArray($this->placeholders);
        $this->pathes_regex = $this->arrayToRegexArray($this->pathes);
    }

    /**
     * Converts a path passed by parameter from services.yaml (which can be an absolute path or relative to project dir)
     * to an absolute path. When a relative path is passed, the directory must exist or null is returned.
     *
     * @internal
     *
     * @param string|null $param_path The parameter value that should be converted to a absolute path
     */
    public function parameterToAbsolutePath(?string $param_path): ?string
    {
        if (null === $param_path) {
            return null;
        }

        $fs = new Filesystem();
        //If current string is already an absolute path, then we have nothing to do
        if ($fs->isAbsolutePath($param_path)) {
            $tmp = realpath($param_path);
            //Disable ressource if path is not existing
            if (false === $tmp) {
                return null;
            }

            return $tmp;
        }

        //Otherwise prepend the project path
        $tmp = realpath($this->project_dir.\DIRECTORY_SEPARATOR.$param_path);

        //If path does not exist then disable the placeholder
        if (false === $tmp) {
            return null;
        }

        //Otherwise return resolved path
        return $tmp;
    }

    /**
     * Converts an relative placeholder filepath (with %MEDIA% or older %BASE%) to an absolute filepath on disk.
     * The directory separator is always /. Relative pathes are not realy possible (.. is striped).
     *
     * @param string $placeholder_path the filepath with placeholder for which the real path should be determined
     *
     * @return string|null The absolute real path of the file, or null if the placeholder path is invalid
     */
    public function placeholderToRealPath(string $placeholder_path): ?string
    {
        //The new attachments use %MEDIA% as placeholders, which is the directory set in media_directory
        //Older path entries are given via %BASE% which was the project root

        $count = 0;
        $placeholder_path = preg_replace($this->placeholders_regex, $this->pathes, $placeholder_path, -1, $count);

        //A valid placeholder can have only one
        if (1 !== $count) {
            return null;
        }

        //If we have now have a placeholder left, the string is invalid:
        if (preg_match('/%\w+%/', $placeholder_path)) {
            return null;
        }

        //Path is invalid if path is directory traversal
        if (false !== strpos($placeholder_path, '..')) {
            return null;
        }

        //Normalize path and remove .. (to prevent directory traversal attack)
        return str_replace(['\\'], ['/'], $placeholder_path);
    }

    /**
     * Converts an real absolute filepath to a placeholder version.
     *
     * @param string $real_path   the absolute path, for which the placeholder version should be generated
     * @param bool   $old_version By default the %MEDIA% placeholder is used, which is directly replaced with the
     *                            media directory. If set to true, the old version with %BASE% will be used, which is the project directory.
     *
     * @return string The placeholder version of the filepath
     */
    public function realPathToPlaceholder(string $real_path, bool $old_version = false): ?string
    {
        $count = 0;

        //Normalize path
        $real_path = str_replace('\\', '/', $real_path);

        if ($old_version) {
            //We need to remove the %MEDIA% placeholder (element 0)
            $pathes = $this->pathes_regex;
            $placeholders = $this->placeholders;
            unset($pathes[0], $placeholders[0]);
            $real_path = preg_replace($pathes, $placeholders, $real_path, -1, $count);
        } else {
            $real_path = preg_replace($this->pathes_regex, $this->placeholders, $real_path, -1, $count);
        }

        if (1 !== $count) {
            return null;
        }

        //If the new string does not begin with a placeholder, it is invalid
        if (! preg_match('/^%\w+%/', $real_path)) {
            return null;
        }

        return $real_path;
    }

    /**
     * The path where uploaded attachments is stored.
     *
     * @return string the absolute path to the media folder
     */
    public function getMediaPath(): string
    {
        return $this->media_path;
    }

    /**
     * The path where secured attachments are stored. Must not be located in public/ folder, so it can only be accessed
     * via the attachment controller.
     *
     * @return string the absolute path to the secure path
     */
    public function getSecurePath(): string
    {
        return $this->secure_path;
    }

    /**
     * The string where the builtin footprints are stored.
     *
     * @return string|null The absolute path to the footprints folder. Null if built footprints were disabled.
     */
    public function getFootprintsPath(): ?string
    {
        return $this->footprints_path;
    }

    /**
     * The string where the builtin 3D models are stored.
     *
     * @return string|null The absolute path to the models folder. Null if builtin models were disabled.
     */
    public function getModelsPath(): ?string
    {
        return $this->models_path;
    }

    /**
     * Create an array usable for preg_replace out of an array of placeholders or pathes.
     * Slashes and other chars become escaped.
     * For example: '%TEST%' becomes '/^%TEST%/'.
     */
    protected function arrayToRegexArray(array $array): array
    {
        $ret = [];

        foreach ($array as $item) {
            $item = str_replace(['\\'], ['/'], $item);
            $ret[] = '/'.preg_quote($item, '/').'/';
        }

        return $ret;
    }
}
