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
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('partdb:kicad:populate', 'Populate KiCad footprint paths and symbol paths for footprints and categories')]
final class PopulateKicadCommand extends Command
{
    private const DEFAULT_MAPPING_FILE = 'assets/commands/kicad_populate_default_mappings.json';

    public function __construct(private readonly EntityManagerInterface $entityManager, #[Autowire("%kernel.project_dir%")] private readonly string $projectDir)
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
            ->addOption('mapping-file', null, InputOption::VALUE_REQUIRED, 'Path to a JSON file with custom mappings (merges with built-in defaults)')
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
        $mappingFile = $input->getOption('mapping-file');

        // If neither specified, do both
        $doFootprints = !$categoriesOnly || $footprintsOnly;
        $doCategories = !$footprintsOnly || $categoriesOnly;

        if ($list) {
            $this->listCurrentValues($io);
            return Command::SUCCESS;
        }

        // Load mappings: start with built-in defaults, then merge user-supplied file
        ['footprints' => $footprintMappings, 'categories' => $categoryMappings] = $this->getDefaultMappings();

        if ($mappingFile !== null) {
            $customMappings = $this->loadMappingFile($mappingFile, $io);
            if ($customMappings === null) {
                return Command::FAILURE;
            }
            if (isset($customMappings['footprints']) && is_array($customMappings['footprints'])) {
                // User mappings take priority (overwrite defaults)
                $footprintMappings = array_merge($footprintMappings, $customMappings['footprints']);
                $io->text(sprintf('Loaded %d custom footprint mappings from %s', count($customMappings['footprints']), $mappingFile));
            }
            if (isset($customMappings['categories']) && is_array($customMappings['categories'])) {
                $categoryMappings = array_merge($categoryMappings, $customMappings['categories']);
                $io->text(sprintf('Loaded %d custom category mappings from %s', count($customMappings['categories']), $mappingFile));
            }
        }

        if ($dryRun) {
            $io->note('DRY RUN MODE - No changes will be made');
        }

        $totalUpdated = 0;

        if ($doFootprints) {
            $updated = $this->updateFootprints($io, $dryRun, $force, $footprintMappings);
            $totalUpdated += $updated;
        }

        if ($doCategories) {
            $updated = $this->updateCategories($io, $dryRun, $force, $categoryMappings);
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

    private function updateFootprints(SymfonyStyle $io, bool $dryRun, bool $force, array $mappings): int
    {
        $io->section('Updating Footprint Entities');

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

            // Check for exact match on name first, then try alternative names
            $matchedValue = $this->findFootprintMapping($mappings, $name, $footprint->getAlternativeNames());

            if ($matchedValue !== null) {
                $io->text(sprintf('  %s: %s -> %s', $name, $currentValue ?? '(empty)', $matchedValue));

                if (!$dryRun) {
                    $footprint->getEdaInfo()->setKicadFootprint($matchedValue);
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

    private function updateCategories(SymfonyStyle $io, bool $dryRun, bool $force, array $mappings): int
    {
        $io->section('Updating Category Entities');

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

            // Check for matches using the pattern-based mappings (also check alternative names)
            $matchedValue = $this->findCategoryMapping($mappings, $name, $category->getAlternativeNames());

            if ($matchedValue !== null) {
                $io->text(sprintf('  %s: %s -> %s', $name, $currentValue ?? '(empty)', $matchedValue));

                if (!$dryRun) {
                    $category->getEdaInfo()->setKicadSymbol($matchedValue);
                }
                $updated++;
            } else {
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

    /**
     * Loads a JSON mapping file and returns the parsed data.
     * Expected format: {"footprints": {"Name": "KiCad:Path"}, "categories": {"Pattern": "KiCad:Path"}}
     *
     * @return array|null The parsed mappings, or null on error
     */
    private function loadMappingFile(string $path, SymfonyStyle $io): ?array
    {
        if (!file_exists($path)) {
            $io->error(sprintf('Mapping file not found: %s', $path));
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $io->error(sprintf('Could not read mapping file: %s', $path));
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            $io->error(sprintf('Invalid JSON in mapping file: %s', $path));
            return null;
        }

        return $data;
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
     * Finds a footprint mapping by checking the entity name and its alternative names.
     * Footprints use exact matching.
     *
     * @param array<string, string> $mappings
     * @param string $name The primary name of the footprint
     * @param string|null $alternativeNames Comma-separated alternative names
     * @return string|null The matched KiCad path, or null if no match found
     */
    private function findFootprintMapping(array $mappings, string $name, ?string $alternativeNames): ?string
    {
        // Check primary name
        if (isset($mappings[$name])) {
            return $mappings[$name];
        }

        // Check alternative names
        if ($alternativeNames !== null && $alternativeNames !== '') {
            foreach (explode(',', $alternativeNames) as $altName) {
                $altName = trim($altName);
                if ($altName !== '' && isset($mappings[$altName])) {
                    return $mappings[$altName];
                }
            }
        }

        return null;
    }

    /**
     * Finds a category mapping by checking the entity name and its alternative names.
     * Categories use pattern-based matching (case-insensitive contains).
     *
     * @param array<string, string> $mappings
     * @param string $name The primary name of the category
     * @param string|null $alternativeNames Comma-separated alternative names
     * @return string|null The matched KiCad symbol path, or null if no match found
     */
    private function findCategoryMapping(array $mappings, string $name, ?string $alternativeNames): ?string
    {
        // Check primary name against all patterns
        foreach ($mappings as $pattern => $kicadSymbol) {
            if ($this->matchesPattern($name, $pattern)) {
                return $kicadSymbol;
            }
        }

        // Check alternative names against all patterns
        if ($alternativeNames !== null && $alternativeNames !== '') {
            foreach (explode(',', $alternativeNames) as $altName) {
                $altName = trim($altName);
                if ($altName === '') {
                    continue;
                }
                foreach ($mappings as $pattern => $kicadSymbol) {
                    if ($this->matchesPattern($altName, $pattern)) {
                        return $kicadSymbol;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Returns the default mappings for footprints and categories.
     * @return array{footprints: array<string, string>, categories: array<string, string>}
     * @throws \JsonException
     */
    private function getDefaultMappings(): array
    {
        $path = $this->projectDir . '/' . self::DEFAULT_MAPPING_FILE;
        $content = file_get_contents($path);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
