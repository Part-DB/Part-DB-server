<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-server).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\Tools;

use App\Entity\Parts\Part;

/**
 * Best-effort classification of a part as a resistor / SMD resistor / capacitor, together with its
 * electrical value, from its parameters, footprint, category and name. Used by the value calculator
 * (to pre-fill) and the bulk image generator (to classify a whole assortment).
 */
final readonly class ComponentValueGuesser
{
    /** Imperial SMD chip package codes that mark a part as surface-mount. */
    private const SMD_PACKAGES = ['01005', '0201', '0402', '0603', '0805', '1206', '1210', '2010', '2512'];

    /** Imperial -> metric size, for building KiCad SMD footprint names. */
    private const SMD_METRIC = [
        '0201' => '0603', '0402' => '1005', '0603' => '1608', '0805' => '2012',
        '1206' => '3216', '1210' => '3225', '2010' => '5025', '2512' => '6332',
    ];

    /** Named THT/SMD diode/LED package -> KiCad footprint. Keyed by the token {@see detectDiodePackage()} returns. */
    private const DIODE_PACKAGE_FOOTPRINTS = [
        'DO-41' => 'Diode_THT:D_DO-41_SOD81_P10.16mm_Horizontal',
        'DO-35' => 'Diode_THT:D_DO-35_SOD27_P7.62mm_Horizontal',
        'DO-15' => 'Diode_THT:D_DO-15_P12.70mm_Horizontal',
        'DO-201' => 'Diode_THT:D_DO-201AD_P15.24mm_Horizontal',
        'SOD-123' => 'Diode_SMD:D_SOD-123',
        'SOD-323' => 'Diode_SMD:D_SOD-323',
        'SOT-23' => 'Diode_SMD:D_SOT-23',
        'SMA' => 'Diode_SMD:D_SMA',
        'SMB' => 'Diode_SMD:D_SMB',
        'SMC' => 'Diode_SMD:D_SMC',
        //LED dome sizes (only ever matched when the subtype is 'led', see detectDiodePackage()).
        '3MM' => 'LED_THT:LED_D3.0mm',
        '5MM' => 'LED_THT:LED_D5.0mm',
        '10MM' => 'LED_THT:LED_D10.0mm',
    ];

    /**
     * Suggested KiCad/EDA settings for a classified component. Uses the detected package for SMD
     * parts and the lead pitch / body diameter for through-hole ceramic discs.
     *
     * @param array{type: string, package: string|null, pitch: float|null, diameter: float|null, subtype?: string|null} $guess
     *
     * @return array{symbol: string, reference: string, footprint: string|null}
     */
    public function edaSuggestion(array $guess): array
    {
        $type = $guess['type'];
        $package = $guess['package'] ?? null;

        if ($type === 'capacitor' || $type === 'smd_capacitor') {
            //SMD (MLCC) capacitors get a chip footprint; through-hole discs are sized by pitch/diameter.
            if ($type === 'smd_capacitor' && $package !== null && isset(self::SMD_METRIC[$package])) {
                $footprint = 'Capacitor_SMD:C_'.$package.'_'.self::SMD_METRIC[$package].'Metric';
            } else {
                $footprint = $this->capDiscFootprint($guess['pitch'] ?? null, $guess['diameter'] ?? null);
            }

            return ['symbol' => 'Device:C', 'reference' => 'C', 'footprint' => $footprint];
        }

        if ($type === 'inductor' || $type === 'smd_inductor') {
            //SMD inductors get a chip footprint; through-hole ones are left blank (editable afterwards).
            $footprint = null;
            if ($type === 'smd_inductor' && $package !== null && isset(self::SMD_METRIC[$package])) {
                $footprint = 'Inductor_SMD:L_'.$package.'_'.self::SMD_METRIC[$package].'Metric';
            }

            return ['symbol' => 'Device:L', 'reference' => 'L', 'footprint' => $footprint];
        }

        if ($type === 'diode') {
            $subtype = $guess['subtype'] ?? 'diode';
            $symbol = match ($subtype) {
                'led' => 'Device:LED',
                'zener' => 'Device:D_Zener',
                'schottky' => 'Device:D_Schottky',
                'tvs' => 'Device:D_TVS',
                default => 'Device:D',
            };
            //A named package (DO-41, SOD-123, a 3mm LED dome, ...) maps to a fixed footprint; an
            //imperial chip code (0805, ...) is only meaningful for SMD diodes/LEDs sized like a chip.
            $footprint = null;
            if ($package !== null) {
                $footprint = self::DIODE_PACKAGE_FOOTPRINTS[$package] ?? null;
                if ($footprint === null && isset(self::SMD_METRIC[$package])) {
                    $prefix = $subtype === 'led' ? 'LED_SMD:LED' : 'Diode_SMD:D';
                    $footprint = $prefix.'_'.$package.'_'.self::SMD_METRIC[$package].'Metric';
                }
            }

            return ['symbol' => $symbol, 'reference' => 'D', 'footprint' => $footprint];
        }

        if ($type === 'smd_resistor' && $package !== null && isset(self::SMD_METRIC[$package])) {
            $footprint = 'Resistor_SMD:R_'.$package.'_'.self::SMD_METRIC[$package].'Metric';
        } else {
            //Through-hole resistor: default to the common 1/4 W axial footprint (editable afterwards).
            $footprint = 'Resistor_THT:R_Axial_DIN0207_L6.3mm_D2.5mm_P7.62mm_Horizontal';
        }

        return ['symbol' => 'Device:R', 'reference' => 'R', 'footprint' => $footprint];
    }

    /**
     * Picks a standard KiCad through-hole ceramic disc footprint for the given lead pitch (mm) and
     * body diameter (mm), choosing the pitch bucket (2.50 / 5.00 / 7.50 mm) then the nearest disc
     * diameter within it. Defaults to a 5 mm pitch / 5 mm disc.
     */
    private function capDiscFootprint(?float $pitch, ?float $diameter): string
    {
        $p = $pitch ?? 5.0;
        $d = $diameter ?? 5.0;

        if ($p < 3.8) {
            $options = [
                [3.0, 'Capacitor_THT:C_Disc_D3.0mm_W1.6mm_P2.50mm'],
                [3.8, 'Capacitor_THT:C_Disc_D3.8mm_W2.6mm_P2.50mm'],
                [5.0, 'Capacitor_THT:C_Disc_D5.0mm_W2.5mm_P2.50mm'],
            ];
        } elseif ($p < 6.5) {
            $options = [
                [5.0, 'Capacitor_THT:C_Disc_D5.0mm_W2.5mm_P5.00mm'],
                [6.0, 'Capacitor_THT:C_Disc_D6.0mm_W2.5mm_P5.00mm'],
                [7.5, 'Capacitor_THT:C_Disc_D7.5mm_W2.5mm_P5.00mm'],
                [10.0, 'Capacitor_THT:C_Disc_D10.0mm_W2.5mm_P5.00mm'],
            ];
        } else {
            $options = [
                [7.5, 'Capacitor_THT:C_Disc_D7.5mm_W5.0mm_P7.50mm'],
                [10.5, 'Capacitor_THT:C_Disc_D10.5mm_W5.0mm_P7.50mm'],
            ];
        }

        $best = $options[0][1];
        $bestDelta = INF;
        foreach ($options as [$dia, $fp]) {
            $delta = abs($dia - $d);
            if ($delta < $bestDelta) {
                $bestDelta = $delta;
                $best = $fp;
            }
        }

        return $best;
    }

    /**
     * Classifies a part.
     *
     * @return array{type: 'resistor'|'smd_resistor'|'capacitor'|'smd_capacitor'|'inductor'|'smd_inductor'|'diode', value: float,
     *               package: string|null, voltage: int|null, tolerance: string|null, pitch: float|null,
     *               diameter: float|null, power: float|null, ppm: int|null, color: string|null,
     *               subtype: string|null}|null
     *              value is ohms (resistors), farads (capacitors), henries (inductors) or the rated/forward
     *              voltage (diodes, 0 if unknown); subtype names the diode kind. Null if it can't be classified.
     */
    public function guess(Part $part): ?array
    {
        //Unambiguous diode part numbers (1N4148, BAT54, BZX…) are recognized first: their "1N…" style
        //would otherwise be misread as an RKM value (e.g. "1N4148" -> 1.4148 nF).
        $text = mb_strtolower($part->getName().' '.$part->getDescription());
        $pnSubtype = $this->detectDiodePartNumber($text);
        if ($pnSubtype !== null) {
            return $this->buildDiodeGuess($part, $pnSubtype);
        }

        [$ohms, $farads, $henries] = $this->extractValue($part);
        $tolerance = $this->detectTolerance($part);
        $color = $this->detectBodyColor($part);

        if ($ohms !== null && $ohms > 0) {
            $package = $this->detectSmdPackage($part);

            return [
                'type' => $package !== null ? 'smd_resistor' : 'resistor',
                'value' => $ohms,
                'package' => $package,
                'voltage' => $this->detectVoltage($part),
                'tolerance' => $tolerance,
                'pitch' => null,
                'diameter' => null,
                'power' => $this->detectPower($part),
                'ppm' => $this->detectPpm($part),
                'color' => $color,
                'subtype' => null,
            ];
        }

        if ($farads !== null && $farads > 0) {
            //A surface-mount cap is drawn as an (unmarked) MLCC chip; a THT one as a ceramic disc.
            $package = $this->detectSmdPackage($part);

            return [
                'type' => $package !== null ? 'smd_capacitor' : 'capacitor',
                'value' => $farads,
                'package' => $package,
                'voltage' => $this->detectVoltage($part),
                'tolerance' => $tolerance,
                'pitch' => $package !== null ? null : $this->detectPitch($part),
                'diameter' => $package !== null ? null : $this->detectDiameter($part),
                'power' => null,
                'ppm' => null,
                'color' => $color,
                'subtype' => null,
            ];
        }

        if ($henries !== null && $henries > 0) {
            //A surface-mount inductor is drawn as a molded chip with a µH code; a THT one as a colour barrel.
            $package = $this->detectSmdPackage($part);

            return [
                'type' => $package !== null ? 'smd_inductor' : 'inductor',
                'value' => $henries,
                'package' => $package,
                'voltage' => $this->detectVoltage($part),
                'tolerance' => $tolerance,
                'pitch' => null,
                'diameter' => null,
                'power' => null,
                'ppm' => null,
                'color' => $color,
                'subtype' => null,
            ];
        }

        //Diodes named only by a keyword ("diode", "LED", …) are a fallback after the passive checks,
        //so "220R resistor for LED" stays a resistor (its resistance is detected first).
        $kwSubtype = $this->detectDiodeKeyword($text);
        if ($kwSubtype !== null) {
            return $this->buildDiodeGuess($part, $kwSubtype);
        }

        return null;
    }

    /**
     * Recognises a diode from an unambiguous part-number family (1N4148, 1N400x, BAT54, BZX…, SMBJ…).
     * These are checked before the passive-value parsing, as their "1N…" style would otherwise be
     * misread as an RKM capacitance/resistance.
     *
     * @return 'led'|'zener'|'schottky'|'tvs'|'diode'|null
     */
    private function detectDiodePartNumber(string $text): ?string
    {
        //Zener families: BZX/BZV/BZT and 1N47xx / 1N52xx (with an optional letter suffix, e.g. 1N4733A).
        if (preg_match('/\bbz[xvt]\d/u', $text) === 1
            || preg_match('/\b1n(4[67]\d{2}|52\d{2})[a-z]?\b/u', $text) === 1) {
            return 'zener';
        }
        //Schottky families: BAT, 1N58xx, MBR.
        if (preg_match('/\bbat\d/u', $text) === 1
            || preg_match('/\b1n58\d{2}[a-z]?\b/u', $text) === 1
            || preg_match('/\bmbr\d/u', $text) === 1) {
            return 'schottky';
        }
        //TVS families: SMAJ/SMBJ, P6KE, 1.5KE (the KE families carry a voltage suffix, e.g. P6KE18A).
        if (preg_match('/\bsm[ab]j\d/u', $text) === 1
            || preg_match('/\bp6ke\d/u', $text) === 1
            || preg_match('/\b1\.5ke\d/u', $text) === 1) {
            return 'tvs';
        }
        //General-purpose / rectifier families: 1N4148, 1N400x, 1N914, BAV/BAS (optional letter suffix).
        if (preg_match('/\b1n(400\d|4148|914)[a-z]?\b/u', $text) === 1
            || preg_match('/\bba[vs]\d/u', $text) === 1) {
            return 'diode';
        }

        return null;
    }

    /**
     * Recognises a diode from a descriptive keyword ("diode", "LED", "Zener", "Schottky", "TVS").
     * Weaker than a part-number match, so this is only consulted after the passive-value checks.
     *
     * @return 'led'|'zener'|'schottky'|'tvs'|'diode'|null
     */
    private function detectDiodeKeyword(string $text): ?string
    {
        if (preg_match('/\bled\b/u', $text) === 1 || preg_match('/light[- ]emitting/u', $text) === 1) {
            return 'led';
        }
        if (preg_match('/\bzener\b/u', $text) === 1) {
            return 'zener';
        }
        if (preg_match('/\bschottky\b/u', $text) === 1) {
            return 'schottky';
        }
        if (preg_match('/\btvs\b/u', $text) === 1 || preg_match('/transient|transil/u', $text) === 1) {
            return 'tvs';
        }
        if (preg_match('/\bdiode\b/u', $text) === 1 || preg_match('/\brectifier\b/u', $text) === 1) {
            return 'diode';
        }

        return null;
    }

    /**
     * Recognises a named THT/SMD diode or LED package (DO-41, SOD-123, a 3/5/10 mm LED dome, ...)
     * from the footprint name / name / description, else falls back to an imperial chip code
     * (0805, ...) for diodes/LEDs labelled like a resistor chip. The LED dome sizes are only
     * meaningful (and only checked) when $subtype is 'led'.
     */
    private function detectDiodePackage(Part $part, string $subtype): ?string
    {
        $haystacks = [];
        if ($part->getFootprint() !== null) {
            $haystacks[] = $part->getFootprint()->getName();
        }
        $haystacks[] = $part->getName();
        $haystacks[] = $part->getDescription();

        $patterns = [
            'DO-41' => '/\bdo[\s-]?41\b/iu',
            'DO-35' => '/\bdo[\s-]?35\b/iu',
            'DO-15' => '/\bdo[\s-]?15\b/iu',
            'DO-201' => '/\bdo[\s-]?201\w*\b/iu',
            'SOD-123' => '/\bsod[\s-]?123\b/iu',
            'SOD-323' => '/\bsod[\s-]?323\b/iu',
            'SOT-23' => '/\bsot[\s-]?23\b/iu',
            'SMA' => '/\bsma\b/iu',
            'SMB' => '/\bsmb\b/iu',
            'SMC' => '/\bsmc\b/iu',
        ];

        foreach ($haystacks as $text) {
            if ($text === '') {
                continue;
            }
            foreach ($patterns as $token => $pattern) {
                if (preg_match($pattern, $text) === 1) {
                    return $token;
                }
            }
        }

        if ($subtype === 'led') {
            foreach ($haystacks as $text) {
                if ($text !== '' && preg_match('/\b(3|5|10)\s?mm\b/iu', $text, $m) === 1) {
                    return $m[1].'MM';
                }
            }
        }

        return $this->detectSmdPackage($part);
    }

    /**
     * Extracts a recognisable diode/rectifier part-number marking (e.g. "1N4001", "BAT54") from the
     * name/description, for printing on the generated drawing. Not used for LEDs, which aren't
     * normally marked with their part number.
     */
    private function detectDiodeMarking(Part $part): ?string
    {
        $text = $part->getName().' '.$part->getDescription();
        if (preg_match('/\b(1N\d{3,4}[A-Za-z]?|BZX\d{2}[A-Za-z0-9]*|BAT\d{2,3}[A-Za-z]?|BAV\d{2,3}|BAS\d{2,3}|MBR\d+[A-Za-z]?|SMBJ\d+[A-Za-z]?|SMAJ\d+[A-Za-z]?|P6KE\d+[A-Za-z]?)\b/u', $text, $m) === 1) {
            return mb_strtoupper($m[1]);
        }

        return null;
    }

    /**
     * Builds the classification array for a diode of the given kind, filling in the emission colour
     * (LEDs) or rated/forward voltage (Zener / TVS) where they can be read from the part.
     *
     * @param 'led'|'zener'|'schottky'|'tvs'|'diode' $subtype
     *
     * @return array{type: 'diode', value: float, package: string|null, voltage: int|null, tolerance: null,
     *               pitch: null, diameter: null, power: null, ppm: null, color: string|null, subtype: string,
     *               marking: string|null}
     */
    private function buildDiodeGuess(Part $part, string $subtype): array
    {
        $color = $this->detectBodyColor($part);
        if ($subtype === 'led') {
            //The "colour" of an LED is its emission colour; default to a typical red.
            $color ??= '#c0392b';
        }
        $voltage = ($subtype === 'zener' || $subtype === 'tvs') ? $this->detectVoltage($part) : null;

        return [
            'type' => 'diode',
            'value' => (float) ($voltage ?? 0),
            'package' => $this->detectDiodePackage($part, $subtype),
            'voltage' => $voltage,
            'tolerance' => null,
            'pitch' => null,
            'diameter' => null,
            'power' => null,
            'ppm' => null,
            'color' => $color,
            'subtype' => $subtype,
            //LEDs aren't normally marked with their part number, unlike axial diodes/rectifiers.
            'marking' => $subtype !== 'led' ? $this->detectDiodeMarking($part) : null,
        ];
    }

    /** Temperature coefficient in ppm/K (e.g. "50ppm", "±25 ppm/°C") from the name/description, else null. */
    private function detectPpm(Part $part): ?int
    {
        $text = $part->getName().' '.$part->getDescription();
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*ppm/iu', $text, $m) === 1) {
            return (int) round((float) str_replace(',', '.', $m[1]));
        }

        return null;
    }

    /** Rated power in watts (e.g. "0.25 W", "1/4 W", "1W") from the name/description, else null. */
    private function detectPower(Part $part): ?float
    {
        $text = $part->getName().' '.$part->getDescription();
        //Fractional watt, e.g. "1/4 W", "1/2W".
        if (preg_match('#(\d+)\s*/\s*(\d+)\s*W(?![a-zA-Z0-9])#u', $text, $m) === 1 && (int) $m[2] !== 0) {
            return (float) $m[1] / (float) $m[2];
        }
        //Decimal watt, e.g. "0.25 W", "1 W", "0.5W".
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*W(?![a-zA-Z0-9])/u', $text, $m) === 1) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    /** Detects a body colour word (e.g. "blue body") in the name/description; returns a hex colour or null. */
    private function detectBodyColor(Part $part): ?string
    {
        $text = mb_strtolower($part->getName().' '.$part->getDescription());
        //Ordered so more specific words win; each maps to the swatch used by the drawing.
        $colors = [
            'beige' => '#e8d9b5', 'tan' => '#e8d9b5', 'cream' => '#e8d9b5',
            'blue' => '#2f6db0', 'green' => '#2e7d4f', 'red' => '#b34a2f',
            'brown' => '#6b4a2f', 'black' => '#20242a', 'grey' => '#8a9099',
            'gray' => '#8a9099', 'purple' => '#7b4fb0', 'violet' => '#7b4fb0',
            'amber' => '#e0a63a', 'yellow' => '#e0a63a', 'white' => '#e8e8e8',
        ];
        foreach ($colors as $word => $hex) {
            if (preg_match('/\b'.$word.'\b/u', $text) === 1) {
                return $hex;
            }
        }

        return null;
    }

    /** Lead pitch in mm, from a Pitch/RM parameter or the name ("pitch 2.54mm", "RM5"), else null. */
    private function detectPitch(Part $part): ?float
    {
        try {
            foreach ($part->getParameters() as $param) {
                if (preg_match('/pitch|lead spacing|raster|\brm\b|pin distance/u', mb_strtolower($param->getName())) === 1
                    && $param->getValueTypical() !== null && $param->getValueTypical() > 0) {
                    return (float) $param->getValueTypical();
                }
            }
        } catch (\Throwable) {
            //fall through to text parsing
        }

        $text = $part->getName().' '.$part->getDescription();
        if (preg_match('/(?:pitch|rm|raster)\s*[:=]?\s*(\d+(?:[.,]\d+)?)\s*mm?/iu', $text, $m) === 1
            || preg_match('/(\d+(?:[.,]\d+)?)\s*mm\s*pitch/iu', $text, $m) === 1) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    /** Body diameter in mm, from a Diameter/Size parameter or the name ("⌀5mm"), else null. */
    private function detectDiameter(Part $part): ?float
    {
        try {
            foreach ($part->getParameters() as $param) {
                if (preg_match('/diameter|durchmesser|body size/u', mb_strtolower($param->getName())) === 1
                    && $param->getValueTypical() !== null && $param->getValueTypical() > 0) {
                    return (float) $param->getValueTypical();
                }
            }
        } catch (\Throwable) {
            //fall through to text parsing
        }

        $text = $part->getName().' '.$part->getDescription();
        if (preg_match('/[⌀Ø]\s*(\d+(?:[.,]\d+)?)/u', $text, $m) === 1
            || preg_match('/(?:diameter|durchmesser)\s*[:=]?\s*(\d+(?:[.,]\d+)?)\s*mm?/iu', $text, $m) === 1) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    /** Rated voltage in volts, from a Voltage parameter or the name/description ("50V"), else null. */
    private function detectVoltage(Part $part): ?int
    {
        try {
            foreach ($part->getParameters() as $param) {
                if (preg_match('/voltage|spannung|\bvdc\b/u', mb_strtolower($param->getName())) === 1
                    && $param->getValueTypical() !== null && $param->getValueTypical() > 0) {
                    return (int) round($param->getValueTypical());
                }
            }
        } catch (\Throwable) {
            //fall through to text parsing
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*V(?:DC|AC)?\b/iu', $part->getName().' '.$part->getDescription(), $m) === 1) {
            return (int) round((float) str_replace(',', '.', $m[1]));
        }

        return null;
    }

    /** Tolerance as a display string (e.g. "±10%") from a Tolerance parameter or the name, else null. */
    private function detectTolerance(Part $part): ?string
    {
        try {
            foreach ($part->getParameters() as $param) {
                if (preg_match('/toleran/u', mb_strtolower($param->getName())) !== 1) {
                    continue;
                }
                $text = trim($param->getValueText() ?? '');
                if ($text !== '') {
                    return $text;
                }
                if ($param->getValueTypical() !== null) {
                    return '±'.rtrim(rtrim(sprintf('%.2f', $param->getValueTypical()), '0'), '.').'%';
                }
            }
        } catch (\Throwable) {
            //fall through to text parsing
        }

        $text = $part->getName().' '.$part->getDescription();
        if (preg_match('/±\s*(\d+(?:[.,]\d+)?)\s*%/u', $text, $m) === 1
            || preg_match('/\b(\d+(?:[.,]\d+)?)\s*%/u', $text, $m) === 1) {
            return '±'.str_replace(',', '.', $m[1]).'%';
        }

        return null;
    }

    /**
     * Extracts the resistance (ohms), capacitance (farads) and/or inductance (henries) of a part:
     * first from its parameters, then (for parts named by their value, e.g. "10nF") from the name.
     *
     * @return array{0: float|null, 1: float|null, 2: float|null} [ohms, farads, henries]
     */
    public function extractValue(Part $part): array
    {
        try {
            [$ohms, $farads, $henries] = $this->fromParameters($part);
            if ($ohms !== null || $farads !== null || $henries !== null) {
                return [$ohms, $farads, $henries];
            }

            $text = trim($part->getName().' '.$part->getDescription());

            //Farad and henry units are unambiguous, so a match in the name wins over resistance.
            $farads = $this->parseFaradsFromText($text);
            if ($farads !== null) {
                return [null, $farads, null];
            }
            $henries = $this->parseHenriesFromText($text);
            if ($henries !== null) {
                return [null, null, $henries];
            }

            return [$this->parseOhmsFromText($text), null, null];
        } catch (\Throwable) {
            return [null, null, null];
        }
    }

    /**
     * Reads the resistance/capacitance/inductance from the part's parameters. The number lives in
     * value_typical; its SI prefix is baked into the unit string (e.g. 4.7 + "kΩ" -> 4700 Ω).
     *
     * @return array{0: float|null, 1: float|null, 2: float|null} [ohms, farads, henries]
     */
    private function fromParameters(Part $part): array
    {
        $ohms = null;
        $farads = null;
        $henries = null;

        foreach ($part->getParameters() as $param) {
            $name = mb_strtolower($param->getName());
            $unit = trim($param->getUnit() ?? '');

            $isRes = preg_match('/resist|widerstand|ohm/u', $name) === 1
                || str_contains($unit, 'Ω') || stripos($unit, 'ohm') !== false;
            $isCap = preg_match('/capacit|kapazit|farad/u', $name) === 1
                || preg_match('/^(meg|[pnuµmkMg])?F$/u', $unit) === 1;
            $isInd = preg_match('/induct|induktivit/u', $name) === 1
                || preg_match('/^(meg|[pnuµmk])?H$/u', $unit) === 1;

            if (!$isRes && !$isCap && !$isInd) {
                continue;
            }

            $num = $param->getValueTypical();
            if ($num === null || $num <= 0) {
                continue;
            }

            $prefix = (string) preg_replace('/(Ω|ohms?|F|farads?|H|henr(y|ies))$/iu', '', $unit);
            $value = $num * $this->prefixFactor($prefix);

            if ($isRes && $ohms === null) {
                $ohms = $value;
            } elseif ($isCap && $farads === null) {
                $farads = $value;
            } elseif ($isInd && $henries === null) {
                $henries = $value;
            }
        }

        return [$ohms, $farads, $henries];
    }

    /** Parses a capacitance (farads) out of free text like "10nF", "0.1uF" or "4n7", else null. */
    private function parseFaradsFromText(string $text): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(p|n|u|µ|m)?F\b/iu', $text, $m) === 1) {
            return (float) str_replace(',', '.', $m[1]) * $this->prefixFactor(mb_strtolower($m[2] ?? ''));
        }
        //RKM notation, e.g. 4n7 = 4.7 nF, 2p2 = 2.2 pF.
        if (preg_match('/\b(\d+)(p|n|u|µ)(\d+)\b/iu', $text, $m) === 1) {
            return (float) ($m[1].'.'.$m[3]) * $this->prefixFactor(mb_strtolower($m[2]));
        }

        return null;
    }

    /** Parses an inductance (henries) out of free text like "100µH", "10mH", "4.7uH" or "1H", else null. */
    private function parseHenriesFromText(string $text): ?float
    {
        //Uppercase H only (so "MHz" and "100h" hours don't match); the (?![a-zA-Z0-9]) avoids "MHz".
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(p|n|u|µ|m)?H(?![a-zA-Z0-9])/u', $text, $m) === 1) {
            return (float) str_replace(',', '.', $m[1]) * $this->prefixFactor(mb_strtolower($m[2] ?? ''));
        }

        return null;
    }

    /** Parses a resistance (ohms) out of free text like "4k7", "10k", "470R" or "4.7kΩ", else null. */
    private function parseOhmsFromText(string $text): ?float
    {
        //RKM notation, e.g. 4k7 = 4.7 kΩ, 1R5 = 1.5 Ω, 2M2 = 2.2 MΩ.
        //NB: we use (?<![a-zA-Z0-9]) / (?![a-zA-Z0-9]) instead of \b, because PCRE treats the "Ω"
        //that usually follows (e.g. "4k7Ω") as a word character under /u, so \b would fail there.
        if (preg_match('/(?<![a-zA-Z0-9])(\d+)(R|k|K|M|G)(\d+)(?![a-zA-Z0-9])/u', $text, $m) === 1) {
            $factor = strtoupper($m[2]) === 'R' ? 1.0 : $this->ohmPrefixFactor($m[2]);

            return (float) ($m[1].'.'.$m[3]) * $factor;
        }
        //Number followed by a magnitude letter, e.g. 10k, 4.7M, 470R, or "10 kΩ" / "1 MΩ" with a unit.
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(k|K|M|G|R)(?![a-zA-Z0-9])/u', $text, $m) === 1) {
            if (strtoupper($m[2]) === 'R') {
                return (float) str_replace(',', '.', $m[1]);
            }

            return (float) str_replace(',', '.', $m[1]) * $this->ohmPrefixFactor($m[2]);
        }
        //Explicit ohm unit, e.g. 470Ω, 1 ohm.
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:Ω|ohms?)/iu', $text, $m) === 1) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    /** kilo/mega/giga factor for a resistance magnitude letter ("M" means mega in this context). */
    private function ohmPrefixFactor(string $p): float
    {
        return match (mb_strtolower($p)) {
            'k' => 1e3,
            'm' => 1e6,
            'g' => 1e9,
            default => 1.0,
        };
    }

    /**
     * Returns the SMD package code (e.g. "0603") if the part looks surface-mount, else null.
     * Checks the footprint name, then the part name/description, for a known chip code.
     */
    private function detectSmdPackage(Part $part): ?string
    {
        $haystacks = [];
        if ($part->getFootprint() !== null) {
            $haystacks[] = $part->getFootprint()->getName();
        }
        $haystacks[] = $part->getName();
        $haystacks[] = $part->getDescription();

        foreach ($haystacks as $text) {
            if ($text === '') {
                continue;
            }
            foreach (self::SMD_PACKAGES as $pkg) {
                //Match the code as a standalone token so "0603" doesn't match inside "10603".
                if (preg_match('/(^|[^0-9])'.$pkg.'([^0-9]|$)/', $text) === 1) {
                    return $pkg;
                }
            }
        }

        return null;
    }

    /**
     * SI prefix -> factor. Only the single-letter "m" (milli) vs "M" (mega) distinction is
     * case-sensitive; every other prefix (including the spelled-out "meg" = mega) is matched
     * case-insensitively.
     */
    private function prefixFactor(string $prefix): float
    {
        $prefix = trim($prefix);
        if ($prefix === '' || $prefix === 'M') {
            return $prefix === 'M' ? 1e6 : 1.0;
        }
        if ($prefix === 'm') {
            return 1e-3;
        }

        $factors = ['p' => 1e-12, 'n' => 1e-9, 'u' => 1e-6, 'µ' => 1e-6, 'k' => 1e3, 'meg' => 1e6, 'g' => 1e9];

        return $factors[mb_strtolower($prefix)] ?? 1.0;
    }
}
