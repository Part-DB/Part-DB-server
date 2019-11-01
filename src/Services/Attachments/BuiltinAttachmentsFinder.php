<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony)
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
 *
 */

namespace App\Services\Attachments;

use App\Entity\Attachments\Attachment;
use App\Services\Attachments\AttachmentPathResolver;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * This service is used to find builtin attachment ressources
 * @package App\Services
 */
class BuiltinAttachmentsFinder
{
    protected $pathResolver;
    protected $cache;

    public function __construct(CacheInterface $cache, AttachmentPathResolver $pathResolver)
    {
        $this->pathResolver = $pathResolver;
        $this->cache = $cache;
    }

    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'limit' => 15,  //Given only 15 entries
            //'allowed_extensions' => [], //Filter the filenames. For example ['jpg', 'jpeg'] to only get jpegs.
            //'placeholders' => Attachment::BUILTIN_PLACEHOLDER, //By default use all builtin ressources,
            'empty_returns_all' => false //Return the whole list of ressources when empty keyword is given
        ]);
    }

    /**
     * Returns a list of all builtin ressources.
     * The array is a list of the relative filenames using the %PLACEHOLDERS%.
     * The list contains the files from all configured valid ressoureces.
     * @return array The list of the ressources, or an empty array if an error happened.
     */
    public function getListOfRessources() : array
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
                    if ($tmp !== null) {
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
        } catch (\Psr\Cache\InvalidArgumentException $ex) {
            return [];
        }
    }

    /**
     * Find all ressources which are matching the given keyword and the specified options
     * @param string $keyword The keyword you want to search for.
     * @param array $options Here you can specify some options (see configureOptions for list of options)
     * @param array|null $base_list The list from which should be used as base for filtering.
     * @return array The list of the results matching the specified keyword and options
     */
    public function find(string $keyword, array $options = [], ?array $base_list = []) : array
    {
        if (empty($base_list)) {
            $base_list = $this->getListOfRessources();
        }

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $options = $resolver->resolve($options);



        /*
        if (empty($options['placeholders'])) {
            return [];
        } */

        if ($keyword === '') {
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
        $regex = '/.*' . $keyword . '.*/i';

        return preg_grep($regex, $base_list);
    }

}