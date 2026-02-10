<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('partdb:kicad:populate', 'Populate KiCad footprint paths and symbol paths for footprints and categories')]
class PopulateKicadCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command populates KiCad footprint paths on Footprint entities and KiCad symbol paths on Category entities based on their names.');

        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without applying them')
            ->addOption('footprints', null, InputOption::VALUE_NONE, 'Only update footprint entities')
            ->addOption('categories', null, InputOption::VALUE_NONE, 'Only update category entities')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing values (by default, only empty values are updated)')
            ->addOption('list', null, InputOption::VALUE_NONE, 'List all footprints and categories with their current KiCad values')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $footprintsOnly = $input->getOption('footprints');
        $categoriesOnly = $input->getOption('categories');
        $force = $input->getOption('force');
        $list = $input->getOption('list');

        // If neither specified, do both
        $doFootprints = !$categoriesOnly || $footprintsOnly;
        $doCategories = !$footprintsOnly || $categoriesOnly;

        if ($list) {
            $this->listCurrentValues($io);
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $io->note('DRY RUN MODE - No changes will be made');
        }

        $totalUpdated = 0;

        if ($doFootprints) {
            $updated = $this->updateFootprints($io, $dryRun, $force);
            $totalUpdated += $updated;
        }

        if ($doCategories) {
            $updated = $this->updateCategories($io, $dryRun, $force);
            $totalUpdated += $updated;
        }

        if (!$dryRun && $totalUpdated > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('Updated %d entities. Run "php bin/console cache:clear" to clear the cache.', $totalUpdated));
        } elseif ($dryRun && $totalUpdated > 0) {
            $io->info(sprintf('DRY RUN: Would update %d entities. Run without --dry-run to apply changes.', $totalUpdated));
        } else {
            $io->info('No entities needed updating.');
        }

        return Command::SUCCESS;
    }

    private function listCurrentValues(SymfonyStyle $io): void
    {
        $io->section('Current Footprint KiCad Values');

        $footprintRepo = $this->entityManager->getRepository(Footprint::class);
        /** @var Footprint[] $footprints */
        $footprints = $footprintRepo->findAll();

        $rows = [];
        foreach ($footprints as $footprint) {
            $kicadValue = $footprint->getEdaInfo()->getKicadFootprint();
            $rows[] = [
                $footprint->getId(),
                $footprint->getName(),
                $kicadValue ?? '(empty)',
            ];
        }

        $io->table(['ID', 'Name', 'KiCad Footprint'], $rows);

        $io->section('Current Category KiCad Values');

        $categoryRepo = $this->entityManager->getRepository(Category::class);
        /** @var Category[] $categories */
        $categories = $categoryRepo->findAll();

        $rows = [];
        foreach ($categories as $category) {
            $kicadValue = $category->getEdaInfo()->getKicadSymbol();
            $rows[] = [
                $category->getId(),
                $category->getName(),
                $kicadValue ?? '(empty)',
            ];
        }

        $io->table(['ID', 'Name', 'KiCad Symbol'], $rows);
    }

    private function updateFootprints(SymfonyStyle $io, bool $dryRun, bool $force): int
    {
        $io->section('Updating Footprint Entities');

        $mappings = $this->getFootprintMappings();

        $footprintRepo = $this->entityManager->getRepository(Footprint::class);
        /** @var Footprint[] $footprints */
        $footprints = $footprintRepo->findAll();

        $updated = 0;
        $skipped = [];

        foreach ($footprints as $footprint) {
            $name = $footprint->getName();
            $currentValue = $footprint->getEdaInfo()->getKicadFootprint();

            // Skip if already has value and not forcing
            if (!$force && $currentValue !== null && $currentValue !== '') {
                continue;
            }

            // Check for exact match first
            if (isset($mappings[$name])) {
                $newValue = $mappings[$name];
                $io->text(sprintf('  %s: %s -> %s', $name, $currentValue ?? '(empty)', $newValue));

                if (!$dryRun) {
                    $footprint->getEdaInfo()->setKicadFootprint($newValue);
                }
                $updated++;
            } else {
                // No mapping found
                $skipped[] = $name;
            }
        }

        $io->newLine();
        $io->text(sprintf('Updated: %d footprints', $updated));

        if (count($skipped) > 0) {
            $io->warning(sprintf('No mapping found for %d footprints:', count($skipped)));
            foreach ($skipped as $name) {
                $io->text('  - ' . $name);
            }
        }

        return $updated;
    }

    private function updateCategories(SymfonyStyle $io, bool $dryRun, bool $force): int
    {
        $io->section('Updating Category Entities');

        $mappings = $this->getCategoryMappings();

        $categoryRepo = $this->entityManager->getRepository(Category::class);
        /** @var Category[] $categories */
        $categories = $categoryRepo->findAll();

        $updated = 0;
        $skipped = [];

        foreach ($categories as $category) {
            $name = $category->getName();
            $currentValue = $category->getEdaInfo()->getKicadSymbol();

            // Skip if already has value and not forcing
            if (!$force && $currentValue !== null && $currentValue !== '') {
                continue;
            }

            // Check for matches using the pattern-based mappings
            $matched = false;
            foreach ($mappings as $pattern => $kicadSymbol) {
                if ($this->matchesPattern($name, $pattern)) {
                    $io->text(sprintf('  %s: %s -> %s', $name, $currentValue ?? '(empty)', $kicadSymbol));

                    if (!$dryRun) {
                        $category->getEdaInfo()->setKicadSymbol($kicadSymbol);
                    }
                    $updated++;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $skipped[] = $name;
            }
        }

        $io->newLine();
        $io->text(sprintf('Updated: %d categories', $updated));

        if (count($skipped) > 0) {
            $io->note(sprintf('No mapping found for %d categories (this is often expected):', count($skipped)));
            foreach ($skipped as $name) {
                $io->text('  - ' . $name);
            }
        }

        return $updated;
    }

    private function matchesPattern(string $name, string $pattern): bool
    {
        // Check for exact match
        if ($pattern === $name) {
            return true;
        }

        // Check for case-insensitive contains
        if (stripos($name, $pattern) !== false) {
            return true;
        }

        return false;
    }

    /**
     * Returns footprint name to KiCad footprint path mappings.
     * These are based on KiCad 9.x standard library paths.
     *
     * @return array<int|string, string>
     */
    private function getFootprintMappings(): array
    {
        return [
            // === SOT packages ===
            'SOT-23' => 'Package_TO_SOT_SMD:SOT-23',
            'SOT-23-3' => 'Package_TO_SOT_SMD:SOT-23',
            'SOT-23-5' => 'Package_TO_SOT_SMD:SOT-23-5',
            'SOT-23-6' => 'Package_TO_SOT_SMD:SOT-23-6',
            'SOT-223' => 'Package_TO_SOT_SMD:SOT-223-3_TabPin2',
            'SOT-223-3' => 'Package_TO_SOT_SMD:SOT-223-3_TabPin2',
            'SOT-89' => 'Package_TO_SOT_SMD:SOT-89-3',
            'SOT-89-3' => 'Package_TO_SOT_SMD:SOT-89-3',
            'SOT-323' => 'Package_TO_SOT_SMD:SOT-323_SC-70',
            'SOT-363' => 'Package_TO_SOT_SMD:SOT-363_SC-70-6',
            'TSOT-25' => 'Package_TO_SOT_SMD:SOT-23-5',

            // === SC-70 ===
            'SC-70-5' => 'Package_TO_SOT_SMD:SOT-353_SC-70-5',
            'SC-70-6' => 'Package_TO_SOT_SMD:SOT-363_SC-70-6',

            // === TO packages (through-hole) ===
            'TO-220' => 'Package_TO_SOT_THT:TO-220-3_Vertical',
            'TO-220AB' => 'Package_TO_SOT_THT:TO-220-3_Vertical',
            'TO-220AB-3' => 'Package_TO_SOT_THT:TO-220-3_Vertical',
            'TO-220FP' => 'Package_TO_SOT_THT:TO-220F-3_Vertical',
            'TO-247-3' => 'Package_TO_SOT_THT:TO-247-3_Vertical',
            'TO-92' => 'Package_TO_SOT_THT:TO-92_Inline',
            'TO-92-3' => 'Package_TO_SOT_THT:TO-92_Inline',

            // === TO packages (SMD) ===
            'TO-252' => 'Package_TO_SOT_SMD:TO-252-2',
            'TO-252-2L' => 'Package_TO_SOT_SMD:TO-252-2',
            'TO-252-3L' => 'Package_TO_SOT_SMD:TO-252-3',
            'TO-263' => 'Package_TO_SOT_SMD:TO-263-2',
            'TO-263-2' => 'Package_TO_SOT_SMD:TO-263-2',
            'D2PAK' => 'Package_TO_SOT_SMD:TO-252-2',
            'DPAK' => 'Package_TO_SOT_SMD:TO-252-2',

            // === SOIC ===
            'SOIC-8' => 'Package_SO:SOIC-8_3.9x4.9mm_P1.27mm',
            'ESOP-8' => 'Package_SO:SOIC-8_3.9x4.9mm_P1.27mm',
            'SOIC-14' => 'Package_SO:SOIC-14_3.9x8.7mm_P1.27mm',
            'SOIC-16' => 'Package_SO:SOIC-16_3.9x9.9mm_P1.27mm',

            // === TSSOP / MSOP ===
            'TSSOP-8' => 'Package_SO:TSSOP-8_3x3mm_P0.65mm',
            'TSSOP-14' => 'Package_SO:TSSOP-14_4.4x5mm_P0.65mm',
            'TSSOP-16' => 'Package_SO:TSSOP-16_4.4x5mm_P0.65mm',
            'TSSOP-16L' => 'Package_SO:TSSOP-16_4.4x5mm_P0.65mm',
            'TSSOP-20' => 'Package_SO:TSSOP-20_4.4x6.5mm_P0.65mm',
            'MSOP-8' => 'Package_SO:MSOP-8_3x3mm_P0.65mm',
            'MSOP-10' => 'Package_SO:MSOP-10_3x3mm_P0.5mm',
            'MSOP-16' => 'Package_SO:MSOP-16_3x4mm_P0.5mm',

            // === SOT-5 / SO-5 ===
            'SO-5' => 'Package_TO_SOT_SMD:SOT-23-5',

            // === DIP ===
            'DIP-4' => 'Package_DIP:DIP-4_W7.62mm',
            'DIP-6' => 'Package_DIP:DIP-6_W7.62mm',
            'DIP-8' => 'Package_DIP:DIP-8_W7.62mm',
            'DIP-14' => 'Package_DIP:DIP-14_W7.62mm',
            'DIP-16' => 'Package_DIP:DIP-16_W7.62mm',
            'DIP-18' => 'Package_DIP:DIP-18_W7.62mm',
            'DIP-20' => 'Package_DIP:DIP-20_W7.62mm',
            'DIP-24' => 'Package_DIP:DIP-24_W7.62mm',
            'DIP-28' => 'Package_DIP:DIP-28_W7.62mm',
            'DIP-40' => 'Package_DIP:DIP-40_W15.24mm',

            // === QFN ===
            'QFN-8' => 'Package_DFN_QFN:QFN-8-1EP_3x3mm_P0.65mm_EP1.55x1.55mm',
            'QFN-12(3x3)' => 'Package_DFN_QFN:QFN-12-1EP_3x3mm_P0.5mm_EP1.65x1.65mm',
            'QFN-16' => 'Package_DFN_QFN:QFN-16-1EP_3x3mm_P0.5mm_EP1.45x1.45mm',
            'QFN-20' => 'Package_DFN_QFN:QFN-20-1EP_4x4mm_P0.5mm_EP2.5x2.5mm',
            'QFN-24' => 'Package_DFN_QFN:QFN-24-1EP_4x4mm_P0.5mm_EP2.45x2.45mm',
            'QFN-32' => 'Package_DFN_QFN:QFN-32-1EP_5x5mm_P0.5mm_EP3.45x3.45mm',
            'QFN-48' => 'Package_DFN_QFN:QFN-48-1EP_7x7mm_P0.5mm_EP5.3x5.3mm',

            // === TQFP / LQFP ===
            'TQFP-32' => 'Package_QFP:TQFP-32_7x7mm_P0.8mm',
            'TQFP-44' => 'Package_QFP:TQFP-44_10x10mm_P0.8mm',
            'TQFP-48' => 'Package_QFP:TQFP-48_7x7mm_P0.5mm',
            'TQFP-48(7x7)' => 'Package_QFP:TQFP-48_7x7mm_P0.5mm',
            'TQFP-64' => 'Package_QFP:TQFP-64_10x10mm_P0.5mm',
            'TQFP-100' => 'Package_QFP:TQFP-100_14x14mm_P0.5mm',
            'LQFP-32' => 'Package_QFP:LQFP-32_7x7mm_P0.8mm',
            'LQFP-48' => 'Package_QFP:LQFP-48_7x7mm_P0.5mm',
            'LQFP-64' => 'Package_QFP:LQFP-64_10x10mm_P0.5mm',
            'LQFP-100' => 'Package_QFP:LQFP-100_14x14mm_P0.5mm',

            // === Diode packages ===
            'SOD-123' => 'Diode_SMD:D_SOD-123',
            'SOD-123F' => 'Diode_SMD:D_SOD-123F',
            'SOD-123FL' => 'Diode_SMD:D_SOD-123F',
            'SOD-323' => 'Diode_SMD:D_SOD-323',
            'SOD-523' => 'Diode_SMD:D_SOD-523',
            'SOD-882' => 'Diode_SMD:D_SOD-882',
            'SOD-882D' => 'Diode_SMD:D_SOD-882',
            'SMA(DO-214AC)' => 'Diode_SMD:D_SMA',
            'SMA' => 'Diode_SMD:D_SMA',
            'SMB' => 'Diode_SMD:D_SMB',
            'SMC' => 'Diode_SMD:D_SMC',
            'DO-35' => 'Diode_THT:D_DO-35_SOD27_P7.62mm_Horizontal',
            'DO-35(DO-204AH)' => 'Diode_THT:D_DO-35_SOD27_P7.62mm_Horizontal',
            'DO-41' => 'Diode_THT:D_DO-41_SOD81_P10.16mm_Horizontal',
            'DO-201' => 'Diode_THT:D_DO-201_P15.24mm_Horizontal',

            // === DFN ===
            'DFN-2(0.6x1)' => 'Package_DFN_QFN:DFN-2-1EP_0.6x1.0mm_P0.65mm_EP0.2x0.55mm',
            'DFN1006-2' => 'Package_DFN_QFN:DFN-2_1.0x0.6mm',
            'DFN-6' => 'Package_DFN_QFN:DFN-6-1EP_2x2mm_P0.65mm_EP1x1.6mm',
            'DFN-8' => 'Package_DFN_QFN:DFN-8-1EP_3x2mm_P0.5mm_EP1.3x1.5mm',

            // === Passive component packages (SMD chip sizes) ===
            // Using Resistor_SMD as default - capacitors/inductors can override at part level
            '0201' => 'Resistor_SMD:R_0201_0603Metric',
            '0402' => 'Resistor_SMD:R_0402_1005Metric',
            '0603' => 'Resistor_SMD:R_0603_1608Metric',
            '0805' => 'Resistor_SMD:R_0805_2012Metric',
            '1206' => 'Resistor_SMD:R_1206_3216Metric',
            '1210' => 'Resistor_SMD:R_1210_3225Metric',
            '1812' => 'Resistor_SMD:R_1812_4532Metric',
            '2010' => 'Resistor_SMD:R_2010_5025Metric',
            '2512' => 'Resistor_SMD:R_2512_6332Metric',
            '2917' => 'Resistor_SMD:R_2917_7343Metric',
            '2920' => 'Resistor_SMD:R_2920_7350Metric',

            // === Tantalum / electrolytic capacitor packages ===
            'CASE-A-3216-18(mm)' => 'Capacitor_Tantalum_SMD:CP_EIA-3216-18_Kemet-A',
            'CASE-B-3528-21(mm)' => 'Capacitor_Tantalum_SMD:CP_EIA-3528-21_Kemet-B',
            'CASE-C-6032-28(mm)' => 'Capacitor_Tantalum_SMD:CP_EIA-6032-28_Kemet-C',
            'CASE-D-7343-31(mm)' => 'Capacitor_Tantalum_SMD:CP_EIA-7343-31_Kemet-D',
            'CASE-E-7343-43(mm)' => 'Capacitor_Tantalum_SMD:CP_EIA-7343-43_Kemet-E',

            // === Electrolytic capacitor (SMD) ===
            'SMD,D4xL5.4mm' => 'Capacitor_SMD:CP_Elec_4x5.4',
            'SMD,D5xL5.4mm' => 'Capacitor_SMD:CP_Elec_5x5.4',
            'SMD,D6.3xL5.4mm' => 'Capacitor_SMD:CP_Elec_6.3x5.4',
            'SMD,D6.3xL7.7mm' => 'Capacitor_SMD:CP_Elec_6.3x7.7',
            'SMD,D8xL6.5mm' => 'Capacitor_SMD:CP_Elec_8x6.5',
            'SMD,D8xL10mm' => 'Capacitor_SMD:CP_Elec_8x10',
            'SMD,D10xL10mm' => 'Capacitor_SMD:CP_Elec_10x10',
            'SMD,D10xL10.5mm' => 'Capacitor_SMD:CP_Elec_10x10.5',

            // === Through-hole electrolytic capacitors (radial) ===
            'Through Hole,D5xL11mm' => 'Capacitor_THT:CP_Radial_D5.0mm_P2.00mm',
            'Through Hole,D6.3xL11mm' => 'Capacitor_THT:CP_Radial_D6.3mm_P2.50mm',
            'Through Hole,D8xL11mm' => 'Capacitor_THT:CP_Radial_D8.0mm_P3.50mm',
            'Through Hole,D10xL16mm' => 'Capacitor_THT:CP_Radial_D10.0mm_P5.00mm',
            'Through Hole,D10xL20mm' => 'Capacitor_THT:CP_Radial_D10.0mm_P5.00mm',
            'Through Hole,D12.5xL20mm' => 'Capacitor_THT:CP_Radial_D12.5mm_P5.00mm',

            // === LED packages ===
            'LED 3mm' => 'LED_THT:LED_D3.0mm',
            'LED 5mm' => 'LED_THT:LED_D5.0mm',
            'LED 0603' => 'LED_SMD:LED_0603_1608Metric',
            'LED 0805' => 'LED_SMD:LED_0805_2012Metric',
            'SMD5050-4P' => 'LED_SMD:LED_WS2812B_PLCC4_5.0x5.0mm_P3.2mm',
            'SMD5050-6P' => 'LED_SMD:LED_WS2812B_PLCC4_5.0x5.0mm_P3.2mm',

            // === Crystal packages ===
            'HC-49' => 'Crystal:Crystal_HC49-4H_Vertical',
            'HC-49/U' => 'Crystal:Crystal_HC49-4H_Vertical',
            'HC-49/S' => 'Crystal:Crystal_HC49-U_Vertical',
            'HC-49/US' => 'Crystal:Crystal_HC49-U_Vertical',

            // === USB connectors ===
            'USB-A' => 'Connector_USB:USB_A_Stewart_SS-52100-001_Horizontal',
            'USB-B' => 'Connector_USB:USB_B_OST_USB-B1HSxx_Horizontal',
            'USB-Mini-B' => 'Connector_USB:USB_Mini-B_Lumberg_2486_01_Horizontal',
            'USB-Micro-B' => 'Connector_USB:USB_Micro-B_Molex-105017-0001',
            'USB-C' => 'Connector_USB:USB_C_Receptacle_GCT_USB4085',

            // === Pin headers ===
            '1x2 P2.54mm' => 'Connector_PinHeader_2.54mm:PinHeader_1x02_P2.54mm_Vertical',
            '1x3 P2.54mm' => 'Connector_PinHeader_2.54mm:PinHeader_1x03_P2.54mm_Vertical',
            '1x4 P2.54mm' => 'Connector_PinHeader_2.54mm:PinHeader_1x04_P2.54mm_Vertical',
            '1x5 P2.54mm' => 'Connector_PinHeader_2.54mm:PinHeader_1x05_P2.54mm_Vertical',
            '1x6 P2.54mm' => 'Connector_PinHeader_2.54mm:PinHeader_1x06_P2.54mm_Vertical',
            '1x8 P2.54mm' => 'Connector_PinHeader_2.54mm:PinHeader_1x08_P2.54mm_Vertical',
            '1x10 P2.54mm' => 'Connector_PinHeader_2.54mm:PinHeader_1x10_P2.54mm_Vertical',
            '2x2 P2.54mm' => 'Connector_PinHeader_2.54mm:PinHeader_2x02_P2.54mm_Vertical',
            '2x3 P2.54mm' => 'Connector_PinHeader_2.54mm:PinHeader_2x03_P2.54mm_Vertical',
            '2x4 P2.54mm' => 'Connector_PinHeader_2.54mm:PinHeader_2x04_P2.54mm_Vertical',
            '2x5 P2.54mm' => 'Connector_PinHeader_2.54mm:PinHeader_2x05_P2.54mm_Vertical',
            '2x10 P2.54mm' => 'Connector_PinHeader_2.54mm:PinHeader_2x10_P2.54mm_Vertical',
            '2x20 P2.54mm' => 'Connector_PinHeader_2.54mm:PinHeader_2x20_P2.54mm_Vertical',

            // === SIP packages ===
            'SIP-3-2.54mm' => 'Package_SIP:SIP-3_P2.54mm',
            'SIP-4-2.54mm' => 'Package_SIP:SIP-4_P2.54mm',
            'SIP-5-2.54mm' => 'Package_SIP:SIP-5_P2.54mm',
        ];
    }

    /**
     * Returns category name patterns to KiCad symbol path mappings.
     * Uses pattern matching - order matters (first match wins).
     *
     * @return array<string, string>
     */
    private function getCategoryMappings(): array
    {
        return [
            // More specific matches first
            'Electrolytic' => 'Device:C_Polarized',
            'Polarized' => 'Device:C_Polarized',
            'Tantalum' => 'Device:C_Polarized',
            'Zener' => 'Device:D_Zener',
            'Schottky' => 'Device:D_Schottky',
            'TVS' => 'Device:D_TVS',
            'LED' => 'Device:LED',
            'NPN' => 'Device:Q_NPN_BCE',
            'PNP' => 'Device:Q_PNP_BCE',
            'N-MOSFET' => 'Device:Q_NMOS_GDS',
            'NMOS' => 'Device:Q_NMOS_GDS',
            'N-MOS' => 'Device:Q_NMOS_GDS',
            'P-MOSFET' => 'Device:Q_PMOS_GDS',
            'PMOS' => 'Device:Q_PMOS_GDS',
            'P-MOS' => 'Device:Q_PMOS_GDS',
            'MOSFET' => 'Device:Q_NMOS_GDS', // Default to N-channel
            'JFET' => 'Device:Q_NJFET_DSG',
            'Ferrite' => 'Device:Ferrite_Bead',
            'Crystal' => 'Device:Crystal',
            'Oscillator' => 'Oscillator:Oscillator_Crystal',
            'Fuse' => 'Device:Fuse',
            'Transformer' => 'Device:Transformer_1P_1S',

            // Generic matches (less specific)
            'Resistor' => 'Device:R',
            'Capacitor' => 'Device:C',
            'Inductor' => 'Device:L',
            'Diode' => 'Device:D',
            'Transistor' => 'Device:Q_NPN_BCE',
            'Voltage Regulator' => 'Regulator_Linear:LM317_TO-220',
            'LDO' => 'Regulator_Linear:AMS1117-3.3',
            'Op-Amp' => 'Amplifier_Operational:LM358',
            'Comparator' => 'Comparator:LM393',
            'Optocoupler' => 'Isolator:PC817',
            'Relay' => 'Relay:Relay_DPDT',
            'Connector' => 'Connector:Conn_01x02',
            'Switch' => 'Switch:SW_Push',
            'Button' => 'Switch:SW_Push',
            'Potentiometer' => 'Device:R_POT',
            'Trimpot' => 'Device:R_POT',
            'Thermistor' => 'Device:Thermistor',
            'Varistor' => 'Device:Varistor',
            'Photo' => 'Device:LED', // Photodiode/phototransistor
        ];
    }
}
