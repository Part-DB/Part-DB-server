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
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * This service is used to find builtin attachment ressources.
 * @see \App\Tests\Services\Attachments\BuiltinAttachmentsFinderTest
 */
class BuiltinAttachmentsFinder
{
    public function __construct(protected CacheInterface $cache, protected AttachmentPathResolver $pathResolver)
    {
    }

    /**
     * Returns an array with all builtin footprints, grouped by their folders
     * The array have the form of: [
     *     '/path/to/folder' => [
     *          '%FOOTPRINTS%/path/to/folder/file1.png',
     *          '%FOOTPRINTS%/path/to/folder/file2.png',
     * ]
     */
    public function getListOfFootprintsGroupedByFolder(): array
    {
        $finder = new Finder();
        //We search only files
        $finder->files();
        $finder->in($this->pathResolver->getFootprintsPath());

        $output = [];

        foreach($finder as $file) {
            $folder = $file->getRelativePath();
            //Normalize path (replace \ with /)
            $folder = str_replace('\\', '/', (string) $folder);

            if(!isset($output[$folder])) {
                $output[$folder] = [];
            }
            //Add file to group
            $output[$folder][] = $this->pathResolver->realPathToPlaceholder($file->getPathname());
        }

        return $output;
    }

    /**
     * Returns a list of all builtin ressources.
     * The array is a list of the relative filenames using the %PLACEHOLDERS%.
     * The list contains the files from all configured valid ressoureces.
     *
     * @return array the list of the ressources, or an empty array if an error happened
     */
    public function getListOfRessources(): array
    {
        try {
            return $this->cache->get('attachment_builtin_ressources', function () {
                $results = [];

                $finder = new Finder();
                //We search only files
                $finder->files();
                //Add the folder for each placeholder
                foreach (Attachment::BUILTIN_PLACEHOLDER as $placeholder) {
                    $tmp = $this->pathResolver->placeholderToRealPath($placeholder);
                    //Ignore invalid/deactivated placeholders:
                    if (null !== $tmp) {
                        $finder->in($tmp);
                    }
                }

                foreach ($finder as $file) {
                    $results[] = $this->pathResolver->realPathToPlaceholder($file->getPathname());
                }

                //Sort results ascending
                sort($results);

                return $results;
            });
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    /**
     * Find all ressources which are matching the given keyword and the specified options.
     *
     * @param string     $keyword   the keyword you want to search for
     * @param array      $options   Here you can specify some options (see configureOptions for list of options)
     * @param array|null $base_list the list from which should be used as base for filtering
     *
     * @return array The list of the results matching the specified keyword and options
     */
    public function find(string $keyword, array $options = [], ?array $base_list = []): array
    {
        if ($base_list === null || $base_list === []) {
            $base_list = $this->getListOfRessources();
        }

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        /*
        if (empty($options['placeholders'])) {
            return [];
        } */

        if ('' === $keyword) {
            if ($options['empty_returns_all']) {
                $keyword = '.*';
            } else {
                return [];
            }
        } else {
            //Quote all values in the keyword (user is not allowed to use regex characters)
            $keyword = preg_quote($keyword, '/');
        }

        /*TODO: Implement placheolder and extension filter */
        /* if (!empty($options['allowed_extensions'])) {
            $keyword .= "\.(";
            foreach ($options['allowed_extensions'] as $extension) {
                $keyword .= preg_quote($extension, '/') . '|';
            }
            $keyword .= ')$';
        } */

        //Ignore case
        $regex = '/.*'.$keyword.'.*/i';

        return preg_grep($regex, $base_list);
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'limit' => 15,  //Given only 15 entries
            //'allowed_extensions' => [], //Filter the filenames. For example ['jpg', 'jpeg'] to only get jpegs.
            //'placeholders' => Attachment::BUILTIN_PLACEHOLDER, //By default use all builtin ressources,
            'empty_returns_all' => false, //Return the whole list of ressources when empty keyword is given
        ]);
    }
}
