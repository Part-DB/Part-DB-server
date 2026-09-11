<?php

declare(strict_types=1);

namespace App\Tests\Services\ImportExportSystem;

use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Services\ImportExportSystem\ProjectBomExporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProjectBomExporterTest extends WebTestCase
{
    /**
     * @var ProjectBomExporter
     */
    protected $service;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var list<object>
     */
    private array $entitiesToRemove = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $this->service = self::getContainer()->get(ProjectBomExporter::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->entitiesToRemove) as $entity) {
            if ($this->entityManager->contains($entity)) {
                $this->entityManager->remove($entity);
            }
        }

        $this->entityManager->flush();
        $this->entitiesToRemove = [];

        parent::tearDown();
    }

    public function testGetOrderedEntriesWithEmptyIDList(): void
    {
        $project = new Project();
        $project->setName('Empty BOM export test');

        $this->assertSame(
            [],
            $this->service->getOrderedEntries($project, [])
        );
    }

    public function testGetOrderedEntriesPreservesRequestedOrder(): void
    {
        $project = $this->createProject('Ordered BOM export test');

        $first = $this->createBOMEntry(
            $project,
            'First export entry',
            'R1',
            1.0,
            'First comment'
        );

        $second = $this->createBOMEntry(
            $project,
            'Second export entry',
            'R2',
            2.0,
            'Second comment'
        );

        $this->entityManager->flush();

        $result = $this->service->getOrderedEntries(
            $project,
            [
                $second->getID(),
                $first->getID(),
            ]
        );

        $this->assertCount(2, $result);
        $this->assertSame($second->getID(), $result[0]->getID());
        $this->assertSame($first->getID(), $result[1]->getID());
    }

    public function testGetOrderedEntriesIgnoresEntriesFromAnotherProject(): void
    {
        $firstProject = $this->createProject('First BOM export project');
        $secondProject = $this->createProject('Second BOM export project');

        $firstEntry = $this->createBOMEntry(
            $firstProject,
            'First project entry',
            'R1',
            1.0,
            ''
        );

        $secondEntry = $this->createBOMEntry(
            $secondProject,
            'Second project entry',
            'R2',
            1.0,
            ''
        );

        $this->entityManager->flush();

        $result = $this->service->getOrderedEntries(
            $firstProject,
            [
                $secondEntry->getID(),
                $firstEntry->getID(),
            ]
        );

        $this->assertCount(1, $result);
        $this->assertSame($firstEntry->getID(), $result[0]->getID());
    }

    public function testCreateRowForUnlinkedBOMEntry(): void
    {
        $entry = new ProjectBOMEntry();
        $entry->setName('10k resistor');
        $entry->setComment('Generic resistor used for testing');
        $entry->setMountnames('R1,R2, R3,,');
        $entry->setQuantity(3.0);

        $row = $this->service->createRow(
            $entry,
            [
                'quantity',
                'partId',
                'name',
                'ipn',
                'description',
                'category',
                'footprint',
                'manufacturer',
                'manufacturing_status',
                'mountnames',
                'instockAmount',
                'storelocation',
                'addedDate',
                'lastModified',
                'unknown_column',
            ],
            [
                'BOM Qty.',
                'Part ID',
                'Name',
                'IPN',
                'Description',
                'Category',
                'Footprint',
                'Manufacturer',
                'Manufacturing status',
                'Mount names',
                'In stock',
                'Storage locations',
                'Created at',
                'Last modified',
            ]
        );

        $this->assertSame(3.0, $row['BOM Qty.']);
        $this->assertNull($row['Part ID']);
        $this->assertSame('10k resistor', $row['Name']);
        $this->assertSame('', $row['IPN']);
        $this->assertSame(
            'Generic resistor used for testing',
            $row['Description']
        );
        $this->assertSame('', $row['Category']);
        $this->assertSame('', $row['Footprint']);
        $this->assertSame('', $row['Manufacturer']);
        $this->assertSame('', $row['Manufacturing status']);
        $this->assertSame('R1, R2, R3', $row['Mount names']);
        $this->assertSame('', $row['In stock']);
        $this->assertSame('', $row['Storage locations']);
        $this->assertSame('', $row['Created at']);
        $this->assertSame('', $row['Last modified']);

        /*
         * No corresponding label was supplied for this column, so the
         * exporter falls back to the column name.
         */
        $this->assertArrayHasKey('unknown_column', $row);
        $this->assertSame('', $row['unknown_column']);
    }

    public function testCreateRowForLinkedPart(): void
    {
        $category = $this->getDefaultCategory();

        $part = new Part();
        $part->setName('RC0402FR-071ML');
        $part->setDescription('10k ohm resistor');
        $part->setIpn('TEST-IPN-001');
        $part->setCategory($category);

        $project = $this->createProject('Linked part BOM export test');

        $entry = new ProjectBOMEntry();
        $entry->setProject($project);
        $entry->setPart($part);
        $entry->setName('10k ohm resistor');
        $entry->setComment('BOM entry comment');
        $entry->setMountnames('R1,R2');
        $entry->setQuantity(2.0);

        $this->entityManager->persist($part);
        $this->entityManager->persist($entry);

        $this->entitiesToRemove[] = $entry;
        $this->entitiesToRemove[] = $part;

        $this->entityManager->flush();

        $row = $this->service->createRow(
            $entry,
            [
                'id',
                'quantity',
                'partId',
                'name',
                'ipn',
                'description',
                'category',
                'footprint',
                'manufacturer',
                'manufacturing_status',
                'mountnames',
                'instockAmount',
                'storelocation',
                'price',
                'ext_price',
                'addedDate',
                'lastModified',
            ],
            [
                'BOM ID',
                'BOM Qty.',
                'Part ID',
                'Name',
                'IPN',
                'Description',
                'Category',
                'Footprint',
                'Manufacturer',
                'Manufacturing status',
                'Mount names',
                'In stock',
                'Storage locations',
                'Price',
                'Extended Price',
                'Created at',
                'Last modified',
            ]
        );

        $this->assertSame($entry->getID(), $row['BOM ID']);
        $this->assertSame($part->getID(), $row['Part ID']);
        $this->assertSame(
            'RC0402FR-071ML 10k ohm resistor',
            $row['Name']
        );
        $this->assertSame('TEST-IPN-001', $row['IPN']);
        $this->assertSame('10k ohm resistor', $row['Description']);
        $this->assertSame($category->getName(), $row['Category']);
        $this->assertSame('', $row['Footprint']);
        $this->assertSame('', $row['Manufacturer']);
        $this->assertSame('', $row['Manufacturing status']);
        $this->assertSame('R1, R2', $row['Mount names']);
        $this->assertSame('', $row['Storage locations']);

        /*
         * The exact formatting depends on the configured part unit and
         * currency, so verify that the formatter produced scalar strings.
         */
        $this->assertIsString($row['BOM Qty.']);
        $this->assertIsString($row['In stock']);
        $this->assertIsString($row['Price']);
        $this->assertIsString($row['Extended Price']);

        $this->assertNotSame('', $row['Created at']);
        $this->assertNotSame('', $row['Last modified']);
    }

    public function testCreateRowUsesColumnNameWhenLabelIsMissing(): void
    {
        $entry = new ProjectBOMEntry();
        $entry->setName('Label fallback test');
        $entry->setComment('');
        $entry->setMountnames('U1');
        $entry->setQuantity(1.0);

        $row = $this->service->createRow(
            $entry,
            [
                'name',
                'mountnames',
            ],
            [
                'Component',
            ]
        );

        $this->assertSame('Label fallback test', $row['Component']);
        $this->assertSame('U1', $row['mountnames']);
    }

    private function createProject(string $name): Project
    {
        $project = new Project();
        $project->setName($name);

        $this->entityManager->persist($project);
        $this->entitiesToRemove[] = $project;

        return $project;
    }

    private function createBOMEntry(
        Project $project,
        string $name,
        string $mountnames,
        float $quantity,
        string $comment,
    ): ProjectBOMEntry {
        $entry = new ProjectBOMEntry();
        $entry->setProject($project);
        $entry->setName($name);
        $entry->setMountnames($mountnames);
        $entry->setQuantity($quantity);
        $entry->setComment($comment);

        $this->entityManager->persist($entry);
        $this->entitiesToRemove[] = $entry;

        return $entry;
    }

    private function getDefaultCategory(): Category
    {
        $category = $this->entityManager
            ->getRepository(Category::class)
            ->findOneBy([]);

        if ($category instanceof Category) {
            return $category;
        }

        $category = new Category();
        $category->setName('BOM Export Test Category');

        $this->entityManager->persist($category);
        $this->entitiesToRemove[] = $category;
        $this->entityManager->flush();

        return $category;
    }
}
