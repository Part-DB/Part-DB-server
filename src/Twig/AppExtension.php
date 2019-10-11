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

use App\Entity\Attachments\Attachment;
use App\Entity\Base\DBElement;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\PriceInformations\Currency;
use App\Services\AmountFormatter;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\EntityURLGenerator;
use App\Services\MarkdownParser;
use App\Services\MoneyFormatter;
use App\Services\SIFormatter;
use App\Services\TreeBuilder;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Twig\Cache\CacheInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use s9e\TextFormatter\Bundles\Forum as TextFormatter;
use Twig\TwigFunction;
use Twig\TwigTest;

class AppExtension extends AbstractExtension
{
    protected $entityURLGenerator;
    protected $markdownParser;
    protected $serializer;
    protected $treeBuilder;
    protected $moneyFormatter;
    protected $siformatter;
    protected $amountFormatter;
    protected $attachmentURLGenerator;

    public function __construct(EntityURLGenerator $entityURLGenerator, MarkdownParser $markdownParser,
                                SerializerInterface $serializer, TreeBuilder $treeBuilder,
                                MoneyFormatter $moneyFormatter,
                                SIFormatter $SIFormatter, AmountFormatter $amountFormatter,
                                AttachmentURLGenerator $attachmentURLGenerator)
    {
        $this->entityURLGenerator = $entityURLGenerator;
        $this->markdownParser = $markdownParser;
        $this->serializer = $serializer;
        $this->treeBuilder = $treeBuilder;
        $this->moneyFormatter = $moneyFormatter;
        $this->siformatter = $SIFormatter;
        $this->amountFormatter = $amountFormatter;
        $this->attachmentURLGenerator = $attachmentURLGenerator;
    }

    public function getFilters()
    {
        return [
            new TwigFilter('entityURL', [$this, 'generateEntityURL']),
            new TwigFilter('markdown', [$this->markdownParser, 'parse'], ['pre_escape' => 'html', 'is_safe' => ['html']]),
            new TwigFilter('moneyFormat', [$this, 'formatCurrency']),
            new TwigFilter('siFormat', [$this, 'siFormat']),
            new TwigFilter('amountFormat', [$this, 'amountFormat']),
            new TwigFilter('loginPath', [$this, 'loginPath'])
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
            new TwigFunction('generateTreeData', [$this, 'treeData']),
            new TwigFunction('attachment_thumbnail', [$this->attachmentURLGenerator, 'getThumbnailURL'])
        ];
    }

    public function treeData(DBElement $element, string $type = 'newEdit') : string
    {
        $tree = $this->treeBuilder->typeToTree(get_class($element), $type, $element);
        return $this->serializer->serialize($tree, 'json', ['skip_null_values' => true]);
    }

    /**
     * This function/filter generates an path
     * @param string $path
     * @return string
     */
    public function loginPath(string $path) : string
    {
        $parts = explode("/" ,$path);
        //Remove the part with
        unset($parts[1]);
        return implode("/", $parts);
    }

    public function generateEntityURL(DBElement $entity, string $method = 'info'): string
    {
        return $this->entityURLGenerator->getURL($entity, $method);
    }

    public function formatCurrency($amount, Currency $currency = null, int $decimals = 5)
    {
        return $this->moneyFormatter->format($amount, $currency, $decimals);
    }

    public function siFormat($value, $unit, $decimals = 2, bool $show_all_digits = false)
    {
        return $this->siformatter->format($value, $unit, $decimals, $show_all_digits);
    }

    public function amountFormat($value, ?MeasurementUnit $unit, array $options = [])
    {
        return $this->amountFormatter->format($value, $unit, $options);
    }
}
