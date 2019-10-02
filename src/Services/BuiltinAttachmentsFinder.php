<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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

namespace App\Services;

use App\Entity\Attachments\Attachment;
use App\Services\Attachments\AttachmentPathResolver;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This service is used to find builtin attachment ressources
 * @package App\Services
 */
class BuiltinAttachmentsFinder
{
    protected $pathResolver;

    public function __construct(KernelInterface $kernel, AttachmentPathResolver $pathResolver)
    {
        $this->pathResolver = $pathResolver;
    }

    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'limit' => 15,  //Given only 15 entries
            'filename_filter' => '', //Filter the filenames. For example *.jpg to only get jpegs. Can also be an array
            'placeholders' => Attachment::BUILTIN_PLACEHOLDER, //By default use all builtin ressources
        ]);
    }

    public function find(string $keyword, array $options = []) : array
    {
        $finder = new Finder();

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        if (empty($options['placeholders'])) {
            return [];
        }

        //We search only files
        $finder->files();
        //Add the folder for each placeholder
        foreach ($options['placeholders'] as $placeholder) {
            $tmp = $this->pathResolver->placeholderToRealPath($placeholder);
            //Ignore invalid/deactivated placeholders:
            if ($tmp !== null) {
                $finder->in($tmp);
            }
        }

        //Apply filter if needed
        if (!empty($options['filename_filter'])) {
            $finder->name($options['filename_filter']);
        }

        $finder->path($keyword);

        $arr = [];

        $limit = $options['limit'];

        foreach ($finder as $file) {
            if ($limit <= 0) {
                break;
            }
            $arr[] = $this->pathResolver->realPathToPlaceholder($file->getPathname());
            $limit--;
        }

        return $arr;
    }

}