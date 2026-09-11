<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-server).
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

namespace App\Tests\Services\Tools;

use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Part;
use App\Services\Tools\ComponentValueGuesser;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the classification/parsing logic behind the value calculator and bulk image
 * generator. The guesser has no dependencies, so the tests construct plain Part entities in memory.
 */
class ComponentValueGuesserTest extends TestCase
{
    private ComponentValueGuesser $guesser;

    protected function setUp(): void
    {
        $this->guesser = new ComponentValueGuesser();
    }

    private function part(string $name, ?string $description = null): Part
    {
        $part = new Part();
        $part->setName($name);
        if ($description !== null) {
            $part->setDescription($description);
        }

        return $part;
    }

    private function partWithParameter(string $paramName, float $value, string $unit): Part
    {
        $part = new Part();
        $part->setName('Some part');
        $param = new PartParameter();
        $param->setName($paramName);
        $param->setValueTypical($value);
        $param->setUnit($unit);
        $part->addParameter($param);

        return $part;
    }

    /**
     * @dataProvider resistorValueProvider
     */
    public function testResistorValueFromName(string $name, float $expectedOhms): void
    {
        [$ohms, $farads] = $this->guesser->extractValue($this->part($name));
        self::assertNull($farads, "Expected no capacitance for '$name'");
        self::assertNotNull($ohms, "Expected a resistance for '$name'");
        self::assertEqualsWithDelta($expectedOhms, $ohms, $expectedOhms * 1e-9 + 1e-9);
    }

    public static function resistorValueProvider(): \Generator
    {
        yield 'plain ohm with space' => ['100 Ω', 100.0];
        yield 'plain ohm no space' => ['470Ω', 470.0];
        yield 'R notation' => ['470R', 470.0];
        yield 'kilo with space (regression: Ω is a PCRE word char under /u)' => ['1 kΩ', 1000.0];
        yield 'kilo no space' => ['10kΩ', 10000.0];
        yield 'mega with space' => ['1 MΩ', 1_000_000.0];
        yield 'decimal kilo' => ['4.7 kΩ', 4700.0];
        yield 'RKM kilo' => ['4k7', 4700.0];
        yield 'RKM mega' => ['2M2', 2_200_000.0];
        yield 'bare magnitude letter' => ['10k', 10000.0];
        yield 'realistic imported name' => ['Resistor 10 kΩ 0.25W 1% Metal Film', 10000.0];
        yield 'realistic mega name' => ['Resistor 1 MΩ 0.25W 1% Metal Film', 1_000_000.0];
    }

    /**
     * @dataProvider capacitorValueProvider
     */
    public function testCapacitorValueFromName(string $name, float $expectedFarads): void
    {
        [$ohms, $farads] = $this->guesser->extractValue($this->part($name));
        self::assertNull($ohms, "Expected no resistance for '$name'");
        self::assertNotNull($farads, "Expected a capacitance for '$name'");
        self::assertEqualsWithDelta($expectedFarads, $farads, $expectedFarads * 1e-6);
    }

    public static function capacitorValueProvider(): \Generator
    {
        yield 'nanofarad' => ['10nF', 10e-9];
        yield 'microfarad greek mu' => ['0.1µF', 0.1e-6];
        yield 'picofarad' => ['100pF', 100e-12];
        yield 'RKM nano' => ['4n7', 4.7e-9];
        yield 'RKM pico' => ['2p2', 2.2e-12];
        yield 'named ceramic cap' => ['Ceramic capacitor 100nF', 100e-9];
    }

    public function testValueFromResistanceParameter(): void
    {
        $part = $this->partWithParameter('Resistance', 4.7, 'kΩ');
        [$ohms, $farads] = $this->guesser->extractValue($part);
        self::assertNull($farads);
        self::assertEqualsWithDelta(4700.0, $ohms, 1e-6);
    }

    public function testValueFromCapacitanceParameter(): void
    {
        $part = $this->partWithParameter('Capacitance', 100.0, 'nF');
        [$ohms, $farads] = $this->guesser->extractValue($part);
        self::assertNull($ohms);
        self::assertEqualsWithDelta(100e-9, $farads, 1e-15);
    }

    public function testClassifiesThroughHoleResistor(): void
    {
        $guess = $this->guesser->guess($this->part('Resistor 10 kΩ 0.25W 1% blue body'));
        self::assertNotNull($guess);
        self::assertSame('resistor', $guess['type']);
        self::assertEqualsWithDelta(10000.0, $guess['value'], 1e-6);
        self::assertSame(0.25, $guess['power']);
        self::assertSame('±1%', $guess['tolerance']);
        self::assertSame('#2f6db0', $guess['color']);
    }

    public function testClassifiesSmdResistorFromPackage(): void
    {
        $guess = $this->guesser->guess($this->part('Resistor 4.7 kΩ 0805 1% SMD'));
        self::assertNotNull($guess);
        self::assertSame('smd_resistor', $guess['type']);
        self::assertSame('0805', $guess['package']);
    }

    public function testClassifiesCapacitor(): void
    {
        $guess = $this->guesser->guess($this->part('Ceramic capacitor 100nF 50V'));
        self::assertNotNull($guess);
        self::assertSame('capacitor', $guess['type']);
        self::assertSame(50, $guess['voltage']);
    }

    public function testClassifiesSmdCapacitorFromPackage(): void
    {
        $guess = $this->guesser->guess($this->part('MLCC capacitor 100nF 0805 X7R'));
        self::assertNotNull($guess);
        self::assertSame('smd_capacitor', $guess['type']);
        self::assertSame('0805', $guess['package']);
        self::assertEqualsWithDelta(100e-9, $guess['value'], 1e-15);

        $eda = $this->guesser->edaSuggestion($guess);
        self::assertSame('Device:C', $eda['symbol']);
        self::assertSame('Capacitor_SMD:C_0805_2012Metric', $eda['footprint']);
    }

    public function testUnclassifiableReturnsNull(): void
    {
        self::assertNull($this->guesser->guess($this->part('Arduino Uno R3 development board')));
    }

    public function testClassifiesZeroOhmJumperResistor(): void
    {
        $guess = $this->guesser->guess($this->part('Resistor 0R jumper'));
        self::assertNotNull($guess);
        self::assertSame('resistor', $guess['type']);
        self::assertSame(0.0, $guess['value']);
    }

    public function testClassifiesZeroOhmSmdJumperFromPackage(): void
    {
        $guess = $this->guesser->guess($this->part('Resistor 0R 0805 jumper SMD'));
        self::assertNotNull($guess);
        self::assertSame('smd_resistor', $guess['type']);
        self::assertSame('0805', $guess['package']);
        self::assertSame(0.0, $guess['value']);
    }

    public function testZeroOhmFromResistanceParameter(): void
    {
        $part = $this->partWithParameter('Resistance', 0.0, 'Ω');
        [$ohms, $farads] = $this->guesser->extractValue($part);
        self::assertNull($farads);
        self::assertSame(0.0, $ohms);
    }

    public function testZeroCapacitanceParameterIsIgnored(): void
    {
        // Unlike 0 Ω (a real "jumper" resistor), 0 F isn't a real capacitor value, so it
        // shouldn't be picked up as a classification.
        $part = $this->partWithParameter('Capacitance', 0.0, 'nF');
        [$ohms, $farads] = $this->guesser->extractValue($part);
        self::assertNull($ohms);
        self::assertNull($farads);
    }

    /**
     * @dataProvider powerProvider
     */
    public function testDetectPower(string $name, float $expected): void
    {
        $guess = $this->guesser->guess($this->part($name));
        self::assertNotNull($guess);
        self::assertSame($expected, $guess['power']);
    }

    public static function powerProvider(): \Generator
    {
        yield 'decimal watt' => ['Resistor 1k 0.25W', 0.25];
        yield 'fractional watt' => ['Resistor 1k 1/4W', 0.25];
        yield 'half watt spaced' => ['Resistor 1k 0.5 W', 0.5];
        yield 'one watt' => ['Resistor 1k 1W', 1.0];
    }

    /**
     * @dataProvider ppmProvider
     */
    public function testDetectPpm(string $name, ?int $expected): void
    {
        $guess = $this->guesser->guess($this->part($name));
        self::assertNotNull($guess);
        self::assertSame($expected, $guess['ppm']);
    }

    public static function ppmProvider(): \Generator
    {
        yield 'plain ppm' => ['Resistor 1k 50ppm', 50];
        yield 'ppm per celsius' => ['Resistor 1k 100 ppm/°C', 100];
        yield 'no ppm' => ['Resistor 1k', null];
    }

    /**
     * @dataProvider toleranceProvider
     */
    public function testDetectTolerance(string $name, ?string $expected): void
    {
        $guess = $this->guesser->guess($this->part($name));
        self::assertNotNull($guess);
        self::assertSame($expected, $guess['tolerance']);
    }

    public static function toleranceProvider(): \Generator
    {
        yield 'plus-minus percent' => ['Resistor 1k ±5%', '±5%'];
        yield 'bare percent' => ['Resistor 1k 1%', '±1%'];
        yield 'sub-percent' => ['Resistor 1k 0.1%', '±0.1%'];
    }

    /**
     * @dataProvider colorProvider
     */
    public function testDetectBodyColor(string $description, ?string $expected): void
    {
        $guess = $this->guesser->guess($this->part('Resistor 1k', $description));
        self::assertNotNull($guess);
        self::assertSame($expected, $guess['color']);
    }

    public static function colorProvider(): \Generator
    {
        yield 'blue body' => ['blue body metal film', '#2f6db0'];
        yield 'green' => ['green body', '#2e7d4f'];
        yield 'no colour word' => ['axial resistor', null];
        yield 'colour word inside another word is ignored' => ['tantalum resistor', null];
    }

    public function testEdaSuggestionForThroughHoleResistor(): void
    {
        $eda = $this->guesser->edaSuggestion(['type' => 'resistor', 'package' => null, 'pitch' => null, 'diameter' => null]);
        self::assertSame('Device:R', $eda['symbol']);
        self::assertSame('R', $eda['reference']);
        self::assertStringContainsString('Resistor_THT:R_Axial', (string) $eda['footprint']);
    }

    public function testEdaSuggestionForSmdResistor(): void
    {
        $eda = $this->guesser->edaSuggestion(['type' => 'smd_resistor', 'package' => '0805', 'pitch' => null, 'diameter' => null]);
        self::assertSame('Device:R', $eda['symbol']);
        self::assertSame('Resistor_SMD:R_0805_2012Metric', $eda['footprint']);
    }

    public function testEdaSuggestionForCapacitorUsesPitch(): void
    {
        $eda = $this->guesser->edaSuggestion(['type' => 'capacitor', 'package' => null, 'pitch' => 5.08, 'diameter' => 5.0]);
        self::assertSame('Device:C', $eda['symbol']);
        self::assertSame('C', $eda['reference']);
        self::assertStringContainsString('P5.00mm', (string) $eda['footprint']);
    }

    /**
     * @dataProvider inductorValueProvider
     */
    public function testClassifiesInductor(string $name, float $expectedHenries): void
    {
        $guess = $this->guesser->guess($this->part($name));
        self::assertNotNull($guess, "Expected '$name' to classify");
        self::assertSame('inductor', $guess['type']);
        self::assertEqualsWithDelta($expectedHenries, $guess['value'], $expectedHenries * 1e-6);
    }

    public static function inductorValueProvider(): \Generator
    {
        yield 'microhenry µ' => ['Inductor 100µH', 100e-6];
        yield 'microhenry u' => ['Choke 4.7uH', 4.7e-6];
        yield 'millihenry' => ['Coil 10mH', 10e-3];
        yield 'nanohenry' => ['100nH inductor', 100e-9];
        yield 'henry' => ['1H filter choke', 1.0];
    }

    public function testInductanceFromParameter(): void
    {
        $part = $this->partWithParameter('Inductance', 100.0, 'µH');
        $guess = $this->guesser->guess($part);
        self::assertNotNull($guess);
        self::assertSame('inductor', $guess['type']);
        self::assertEqualsWithDelta(100e-6, $guess['value'], 1e-12);
    }

    public function testMegahertzIsNotMistakenForInductance(): void
    {
        //"100MHz" must not parse as 100 mH — the (?![a-zA-Z0-9]) guard prevents it.
        self::assertNull($this->guesser->guess($this->part('Crystal oscillator 100MHz')));
    }

    public function testEdaSuggestionForInductor(): void
    {
        $eda = $this->guesser->edaSuggestion(['type' => 'inductor', 'package' => null, 'pitch' => null, 'diameter' => null]);
        self::assertSame('Device:L', $eda['symbol']);
        self::assertSame('L', $eda['reference']);
        self::assertNull($eda['footprint']);
    }

    public function testClassifiesSmdInductorFromPackage(): void
    {
        $guess = $this->guesser->guess($this->part('Inductor 10µH 0805 SMD'));
        self::assertNotNull($guess);
        self::assertSame('smd_inductor', $guess['type']);
        self::assertSame('0805', $guess['package']);
        self::assertEqualsWithDelta(10e-6, $guess['value'], 1e-12);
    }

    public function testEdaSuggestionForSmdInductor(): void
    {
        $eda = $this->guesser->edaSuggestion(['type' => 'smd_inductor', 'package' => '1210', 'pitch' => null, 'diameter' => null]);
        self::assertSame('Device:L', $eda['symbol']);
        self::assertSame('L', $eda['reference']);
        self::assertSame('Inductor_SMD:L_1210_3225Metric', $eda['footprint']);
    }

    /**
     * @dataProvider diodeProvider
     */
    public function testClassifiesDiode(string $name, string $expectedSubtype): void
    {
        $guess = $this->guesser->guess($this->part($name));
        self::assertNotNull($guess, "Expected '$name' to classify");
        self::assertSame('diode', $guess['type']);
        self::assertSame($expectedSubtype, $guess['subtype']);
    }

    public static function diodeProvider(): \Generator
    {
        yield 'led word' => ['LED red 5mm 20mA', 'led'];
        yield 'light emitting' => ['Light-emitting diode green', 'led'];
        yield 'zener word' => ['Zener diode 5.1V', 'zener'];
        yield 'zener BZX family' => ['BZX55C5V1', 'zener'];
        yield 'zener 1N47xx' => ['1N4733A', 'zener'];
        yield 'schottky word' => ['Schottky barrier diode', 'schottky'];
        yield 'schottky BAT family' => ['BAT54', 'schottky'];
        yield 'schottky 1N58xx' => ['1N5819', 'schottky'];
        yield 'tvs word' => ['TVS diode array', 'tvs'];
        yield 'tvs SMBJ family' => ['SMBJ15A', 'tvs'];
        yield 'generic rectifier' => ['Rectifier diode', 'diode'];
        yield '1N4148 small signal' => ['1N4148 switching', 'diode'];
        yield '1N4007 rectifier' => ['1N4007', 'diode'];
        yield 'BAV family' => ['BAV99 dual', 'diode'];
    }

    public function testLedUsesEmissionColor(): void
    {
        $guess = $this->guesser->guess($this->part('LED blue 5mm'));
        self::assertNotNull($guess);
        self::assertSame('diode', $guess['type']);
        self::assertSame('led', $guess['subtype']);
        self::assertSame('#2f6db0', $guess['color']);
    }

    public function testZenerCarriesVoltage(): void
    {
        $guess = $this->guesser->guess($this->part('Zener diode 5.1V 0.5W'));
        self::assertNotNull($guess);
        self::assertSame('zener', $guess['subtype']);
        self::assertSame(5, $guess['voltage']);
    }

    public function testResistorForLedStaysResistor(): void
    {
        //"220R" yields a resistance, which is classified before the diode fallback ever runs.
        $guess = $this->guesser->guess($this->part('220R resistor for LED indicator'));
        self::assertNotNull($guess);
        self::assertSame('resistor', $guess['type']);
    }

    /**
     * @dataProvider diodeEdaProvider
     */
    public function testEdaSuggestionForDiode(string $subtype, string $expectedSymbol): void
    {
        $eda = $this->guesser->edaSuggestion(['type' => 'diode', 'subtype' => $subtype, 'package' => null, 'pitch' => null, 'diameter' => null]);
        self::assertSame($expectedSymbol, $eda['symbol']);
        self::assertSame('D', $eda['reference']);
        self::assertNull($eda['footprint']);
    }

    public static function diodeEdaProvider(): \Generator
    {
        yield 'generic' => ['diode', 'Device:D'];
        yield 'led' => ['led', 'Device:LED'];
        yield 'zener' => ['zener', 'Device:D_Zener'];
        yield 'schottky' => ['schottky', 'Device:D_Schottky'];
        yield 'tvs' => ['tvs', 'Device:D_TVS'];
    }

    public function testEdaSuggestionForSmdDiodeFootprint(): void
    {
        $eda = $this->guesser->edaSuggestion(['type' => 'diode', 'subtype' => 'diode', 'package' => '0805', 'pitch' => null, 'diameter' => null]);
        self::assertSame('Diode_SMD:D_0805_2012Metric', $eda['footprint']);
    }

    public function testEdaSuggestionForSmdLedFootprint(): void
    {
        $eda = $this->guesser->edaSuggestion(['type' => 'diode', 'subtype' => 'led', 'package' => '0805', 'pitch' => null, 'diameter' => null]);
        self::assertSame('LED_SMD:LED_0805_2012Metric', $eda['footprint']);
    }

    public function testDetectsThtDiodePackageAndMarking(): void
    {
        //Real-world case: importing a 1N400x rectifier kit, whose names spell out the THT package.
        $guess = $this->guesser->guess($this->part('1N4001 Rectifier Diode 1A 50V DO-41'));
        self::assertNotNull($guess);
        self::assertSame('diode', $guess['type']);
        self::assertSame('diode', $guess['subtype']);
        self::assertSame('DO-41', $guess['package']);
        self::assertSame('1N4001', $guess['marking']);

        $eda = $this->guesser->edaSuggestion($guess);
        self::assertSame('Diode_THT:D_DO-41_SOD81_P10.16mm_Horizontal', $eda['footprint']);
    }

    public function testDetectsSotSchottkyPackage(): void
    {
        $guess = $this->guesser->guess($this->part('BAT54 Schottky diode SOT-23'));
        self::assertNotNull($guess);
        self::assertSame('schottky', $guess['subtype']);
        self::assertSame('SOT-23', $guess['package']);
        self::assertSame('BAT54', $guess['marking']);

        $eda = $this->guesser->edaSuggestion($guess);
        self::assertSame('Diode_SMD:D_SOT-23', $eda['footprint']);
    }

    public function testDetectsLedDomeSizeFootprint(): void
    {
        $guess = $this->guesser->guess($this->part('LED red 5mm diffused'));
        self::assertNotNull($guess);
        self::assertSame('led', $guess['subtype']);
        self::assertSame('5MM', $guess['package']);
        //LEDs aren't normally printed with a part number.
        self::assertNull($guess['marking']);

        $eda = $this->guesser->edaSuggestion($guess);
        self::assertSame('LED_THT:LED_D5.0mm', $eda['footprint']);
    }

    public function testMarkingNullWhenNoRecognisablePartNumber(): void
    {
        $guess = $this->guesser->guess($this->part('Generic rectifier diode'));
        self::assertNotNull($guess);
        self::assertSame('diode', $guess['subtype']);
        self::assertNull($guess['marking']);
    }
}
