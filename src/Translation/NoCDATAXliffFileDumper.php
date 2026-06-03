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


namespace App\Translation;

use DOMDocument;
use DOMXPath;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Translation\Dumper\FileDumper;
use Symfony\Component\Translation\MessageCatalogue;

/**
 * The goal of this class, is to ensure that the XLIFF dumper does not output CDATA, but instead outputs the text
 * using the normal XML escaping. Crowdin outputs the translations without CDATA, we want to be consistent with that, to
 * prevent unnecessary diffs in the translation files when we update them with translations from Crowdin.
 */
#[AsDecorator("translation.dumper.xliff")]
class NoCDATAXliffFileDumper extends FileDumper
{

    public function __construct(private readonly FileDumper $decorated)
    {

    }

    private function convertCDataToEscapedText(string $xmlContent): string
    {
        $dom = new DOMDocument();
        // Preserve whitespace to keep Symfony's formatting intact
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = true;

        // Load the XML (handle internal errors if necessary)
        $dom->loadXML($xmlContent);

        $xpath = new DOMXPath($dom);
        // Find all CDATA sections
        $cdataNodes = $xpath->query('//node()/comment()|//node()/text()|//node()') ;

        // We specifically want CDATA sections. XPath 1.0 doesn't have a direct
        // "cdata-section()" selector easily, so we iterate through all nodes
        // and check their type.

        $nodesToRemove = [];
        foreach ($xpath->query('//text() | //*') as $node) {
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_CDATA_SECTION_NODE) {
                    // Create a new text node with the content of the CDATA
                    // DOMDocument will automatically escape special chars on save
                    $newTextNode = $dom->createTextNode($child->textContent);
                    $node->replaceChild($newTextNode, $child);
                }
            }
        }

        return $dom->saveXML();
    }

    public function formatCatalogue(MessageCatalogue $messages, string $domain, array $options = []): string
    {
        return $this->convertCDataToEscapedText($this->decorated->formatCatalogue($messages, $domain, $options));
    }

    protected function getExtension(): string
    {
        return $this->decorated->getExtension();
    }
}
