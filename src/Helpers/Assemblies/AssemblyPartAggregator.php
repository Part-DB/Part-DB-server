<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Helpers\Assemblies;

use App\Entity\AssemblySystem\Assembly;
use App\Entity\Parts\Part;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class AssemblyPartAggregator
{
    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * Aggregate the required parts and their total quantities for an assembly.
     *
     * @param Assembly $assembly The assembly to process.
     * @param float $multiplier The quantity multiplier from the parent assembly.
     * @return array Array of parts with their aggregated quantities, keyed by Part ID.
     */
    public function getAggregatedParts(Assembly $assembly, float $multiplier): array
    {
        $aggregatedParts = [];

        // Start processing the assembly recursively
        $this->processAssembly($assembly, $multiplier, $aggregatedParts);

        // Return the final aggregated list of parts
        return $aggregatedParts;
    }

    /**
     * Recursive helper to process an assembly and all its BOM entries.
     *
     * @param Assembly $assembly The current assembly to process.
     * @param float $multiplier The quantity multiplier from the parent assembly.
     * @param array &$aggregatedParts The array to accumulate parts and their quantities.
     */
    private function processAssembly(Assembly $assembly, float $multiplier, array &$aggregatedParts): void
    {
        foreach ($assembly->getBomEntries() as $bomEntry) {
            // If the BOM entry refers to a part, add its quantity
            if ($bomEntry->getPart() instanceof Part) {
                $part = $bomEntry->getPart();

                if (!isset($aggregatedParts[$part->getId()])) {
                    $aggregatedParts[$part->getId()] = [
                        'part' => $part,
                        'assembly' => $assembly,
                        'quantity' => $bomEntry->getQuantity(),
                        'multiplier' => $multiplier,
                    ];
                }
            } elseif ($bomEntry->getReferencedAssembly() instanceof Assembly) {
                // If the BOM entry refers to another assembly, process it recursively
                $this->processAssembly($bomEntry->getReferencedAssembly(), $bomEntry->getQuantity(), $aggregatedParts);
            } else {
                $aggregatedParts[] = [
                    'part' => null,
                    'assembly' => $assembly,
                    'quantity' => $bomEntry->getQuantity(),
                    'multiplier' => $multiplier,
                ];
            }
        }
    }

    /**
     * Exports a hierarchical Bill of Materials (BOM) for assemblies and parts in a readable format,
     * including the multiplier for each part and assembly.
     *
     * @param Assembly $assembly The root assembly to export.
     * @param string $indentationSymbol The symbol used for indentation (e.g., '  ').
     * @param int $initialDepth The starting depth for formatting (default: 0).
     * @return string Human-readable hierarchical BOM list.
     */
    public function exportReadableHierarchy(Assembly $assembly, string $indentationSymbol = '  ', int $initialDepth = 0): string
    {
        // Start building the hierarchy
        $output = '';
        $this->processAssemblyHierarchy($assembly, $initialDepth, 1, $indentationSymbol, $output);

        return $output;
    }

    public function exportReadableHierarchyForPdf(array $assemblyHierarchies): string
    {
        $html = $this->twig->render('assemblies/export_bom_pdf.html.twig', [
            'assemblies' => $assemblyHierarchies,
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Arial', 'normal');

        return $dompdf->output();
    }

    /**
     * Recursive method to process assemblies and their parts.
     *
     * @param Assembly $assembly The current assembly to process.
     * @param int $depth The current depth in the hierarchy.
     * @param float $parentMultiplier The multiplier inherited from the parent (default is 1 for root).
     * @param string $indentationSymbol The symbol used for indentation.
     * @param string &$output The cumulative output string.
     */
    private function processAssemblyHierarchy(Assembly $assembly, int $depth, float $parentMultiplier, string $indentationSymbol, string &$output): void
    {
        // Add the current assembly to the output
        if ($depth === 0) {
            $output .= sprintf(
                "%sAssembly: %s [IPN: %s]\n\n",
                str_repeat($indentationSymbol, $depth),
                $assembly->getName(),
                $assembly->getIpn(),
            );
        } else {
            $output .= sprintf(
                "%sAssembly: %s [IPN: %s, Multiplier: %.2f]\n\n",
                str_repeat($indentationSymbol, $depth),
                $assembly->getName(),
                $assembly->getIpn(),
                $parentMultiplier
            );
        }

        // Gruppiere BOM-Einträge in Kategorien
        $parts = [];
        $referencedAssemblies = [];
        $others = [];

        foreach ($assembly->getBomEntries() as $bomEntry) {
            if ($bomEntry->getPart() instanceof Part) {
                $parts[] = $bomEntry;
            } elseif ($bomEntry->getReferencedAssembly() instanceof Assembly) {
                $referencedAssemblies[] = $bomEntry;
            } else {
                $others[] = $bomEntry;
            }
        }

        if (!empty($parts)) {
            // Process each BOM entry for the current assembly
            foreach ($parts as $bomEntry) {
                $effectiveQuantity = $bomEntry->getQuantity() * $parentMultiplier;

                $output .= sprintf(
                    "%sPart: %s [IPN: %s, MPNR: %s, Quantity: %.2f%s, EffectiveQuantity: %.2f]\n",
                    str_repeat($indentationSymbol, $depth + 1),
                    $bomEntry->getPart()?->getName(),
                    $bomEntry->getPart()?->getIpn() ?? '-',
                    $bomEntry->getPart()?->getManufacturerProductNumber() ?? '-',
                    $bomEntry->getQuantity(),
                    $parentMultiplier > 1 ? sprintf(", Multiplier: %.2f", $parentMultiplier) : '',
                    $effectiveQuantity,
                );
            }

            $output .= "\n";
        }

        foreach ($referencedAssemblies as $bomEntry) {
            // Add referenced assembly details
            $referencedQuantity = $bomEntry->getQuantity() * $parentMultiplier;

            $output .= sprintf(
                "%sReferenced Assembly: %s [IPN: %s, Quantity: %.2f%s, EffectiveQuantity: %.2f]\n",
                str_repeat($indentationSymbol, $depth + 1),
                $bomEntry->getReferencedAssembly()->getName(),
                $bomEntry->getReferencedAssembly()->getIpn() ?? '-',
                $bomEntry->getQuantity(),
                $parentMultiplier > 1 ? sprintf(", Multiplier: %.2f", $parentMultiplier) : '',
                $referencedQuantity,
            );

            // Recurse into the referenced assembly
            $this->processAssemblyHierarchy(
                $bomEntry->getReferencedAssembly(),
                $depth + 2,         // Increase depth for nested assemblies
                $referencedQuantity, // Pass the calculated multiplier
                $indentationSymbol,
                $output
            );
        }

        foreach ($others as $bomEntry) {
            $output .= sprintf(
                "%sOther: %s [Quantity: %.2f, Multiplier: %.2f]\n",
                str_repeat($indentationSymbol, $depth + 1),
                $bomEntry->getName(),
                $bomEntry->getQuantity(),
                $parentMultiplier,
            );
        }
    }

    public function processAssemblyHierarchyForPdf(Assembly $assembly, int $depth, float $quantity, float $parentMultiplier): array
    {
        $result = [
            'name' => $assembly->getName(),
            'ipn' => $assembly->getIpn(),
            'quantity' => $quantity,
            'multiplier' => $depth === 0 ? null : $parentMultiplier,
            'parts' => [],
            'referencedAssemblies' => [],
            'others' => [],
        ];

        foreach ($assembly->getBomEntries() as $bomEntry) {
            if ($bomEntry->getPart() instanceof Part) {
                $result['parts'][] = [
                    'name' => $bomEntry->getPart()->getName(),
                    'ipn' => $bomEntry->getPart()->getIpn(),
                    'quantity' => $bomEntry->getQuantity(),
                    'effectiveQuantity' => $bomEntry->getQuantity() * $parentMultiplier,
                ];
            } elseif ($bomEntry->getReferencedAssembly() instanceof Assembly) {
                $result['referencedAssemblies'][] = $this->processAssemblyHierarchyForPdf(
                    $bomEntry->getReferencedAssembly(),
                    $depth + 1,
                    $bomEntry->getQuantity(),
                    $parentMultiplier * $bomEntry->getQuantity()
                );
            } else {
                $result['others'][] = [
                    'name' => $bomEntry->getName(),
                    'quantity' => $bomEntry->getQuantity(),
                    'multiplier' => $parentMultiplier,
                ];
            }
        }

        return $result;
    }
}
