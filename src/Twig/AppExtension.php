<?php
/**
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/.
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
 */

namespace App\Twig;

use App\Entity\Attachment;
use App\Entity\DBElement;
use App\Services\EntityURLGenerator;
use App\Services\TreeBuilder;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use s9e\TextFormatter\Bundles\Forum as TextFormatter;
use Twig\TwigFunction;
use Twig\TwigTest;

class AppExtension extends AbstractExtension
{
    protected $entityURLGenerator;
    protected $cache;
    protected $serializer;
    protected $treeBuilder;

    public function __construct(EntityURLGenerator $entityURLGenerator, AdapterInterface $cache,
                                SerializerInterface $serializer, TreeBuilder $treeBuilder)
    {
        $this->entityURLGenerator = $entityURLGenerator;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->treeBuilder = $treeBuilder;
    }

    public function getFilters()
    {
        return [
           new TwigFilter('entityURL', [$this, 'generateEntityURL']),
           new TwigFilter('bbCode', [$this, 'parseBBCode'], ['pre_escape' => 'html', 'is_safe' => ['html']]),
       ];
    }

    public function getTests()
    {
        return [
            new TwigTest('instanceof', function ($var, $instance) {
                return $var instanceof $instance;
            } )
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('generateTreeData', [$this, 'treeData'])
        ];
    }

    public function treeData(DBElement $element, string $type = 'newEdit') : string
    {
        $tree = $this->treeBuilder->typeToTree(get_class($element), $type, $element);
        return $this->serializer->serialize($tree, 'json', ['skip_null_values' => true]);
    }

    public function generateEntityURL(DBElement $entity, string $method = 'info'): string
    {
        return $this->entityURLGenerator->getURL($entity, $method);
    }

    public function parseBBCode(string $bbcode): string
    {
        if ('' === $bbcode) {
            return '';
        }

        $item = $this->cache->getItem('bbcode_'.md5($bbcode));
        if (!$item->isHit()) {
            $xml = TextFormatter::parse($bbcode);
            $item->set(TextFormatter::render($xml));
            $this->cache->save($item);
        }

        return $item->get();
    }
}
