<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Services\ImportExportSystem\BOMImporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\File;
use App\Entity\UserSystem\User;

final class ProjectControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    private KernelBrowser $client;

    /**
     * @var list<object>
     */
    private array $entitiesToRemove = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->entityManager = self::getContainer()->get(
            EntityManagerInterface::class
        );

        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['name' => 'admin']);

        $this->assertInstanceOf(
            User::class,
            $user,
            'The admin fixture user was not found.'
        );

        $this->client->loginUser($user);
        $this->client->catchExceptions(false);
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

    public function testExportBOMAsCSV(): void
    {
        $project = $this->createProject('Controller CSV Export Test');

        $this->createBOMEntry(
            $project,
            '10k resistor',
            'R1,R2',
            2.0,
            'Generic resistor'
        );

        $this->createBOMEntry(
            $project,
            '100nF capacitor',
            'C1',
            1.0,
            'Ceramic capacitor'
        );

        $this->entityManager->flush();

        $this->client->request(
            'POST',
            sprintf(
                '/en/project/%d/bom/export',
                $project->getID()
            ),
            $this->getDataTableRequest([
                'quantity',
                'name',
                'description',
                'mountnames',
            ], [
                'BOM Qty.',
                'Name',
                'Description',
                'Mount names',
            ])
        );

        $response = $this->client->getResponse();

        $this->assertTrue(
            $response->isSuccessful(),
            sprintf(
                'The CSV export request failed with HTTP %d.',
                $response->getStatusCode()
            )
        );

        $this->assertSame(
            'text/csv; charset=UTF-8',
            $response->headers->get('Content-Type')
        );

        $contentDisposition = (string) $response->headers->get(
            'Content-Disposition'
        );

        $this->assertStringContainsString(
            'attachment',
            $contentDisposition
        );

        $this->assertStringContainsString(
            '.csv',
            $contentDisposition
        );

        $content = $response->getContent();

        $this->assertNotFalse($content);

        /*
         * Parse the generated CSV so the test verifies the exported data rather
         * than depending on the exact quoting produced by Symfony's CSV encoder.
         */
        $stream = fopen('php://memory', 'r+');

        $this->assertIsResource($stream);

        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv(
            $stream,
            separator: ';',
            enclosure: '"',
            escape: ''
        );

        fclose($stream);

        $this->assertSame(
            [
                'BOM Qty.',
                'Name',
                'Description',
                'Mount names',
            ],
            $header
        );

        $this->assertStringContainsString('10k resistor', $content);
        $this->assertStringContainsString('Generic resistor', $content);
        $this->assertStringContainsString('R1, R2', $content);
        $this->assertStringContainsString('100nF capacitor', $content);
        $this->assertStringContainsString('Ceramic capacitor', $content);
    }

    public function testExportBOMIgnoresPagination(): void
    {
        $project = $this->createProject(
            'Controller Unpaginated CSV Export Test'
        );

        for ($index = 1; $index <= 11; ++$index) {
            $this->createBOMEntry(
                $project,
                sprintf('Pagination test part %02d', $index),
                sprintf('R%d', $index),
                1.0,
                ''
            );
        }

        $this->entityManager->flush();

        $request = $this->getDataTableRequest(
            ['name'],
            ['Name']
        );

        /*
         * Simulate the browser currently displaying the second page with
         * ten rows per page. The controller must override these values.
         */
        $request['start'] = 10;
        $request['length'] = 10;

        $this->client->request(
            'POST',
            sprintf(
                '/en/project/%d/bom/export',
                $project->getID()
            ),
            $request
        );

        $response = $this->client->getResponse();

        $this->assertTrue(
            $response->isSuccessful(),
            sprintf(
                'The CSV export request failed with HTTP %d.',
                $response->getStatusCode()
            )
        );

        $content = $response->getContent();

        $this->assertNotFalse($content);

        $rows = preg_split('/\R/', trim($content));

        $this->assertIsArray($rows);

        $rows = array_values(array_filter(
            $rows,
            static fn(string $row): bool => $row !== ''
        ));

        /*
         * One heading row plus all eleven BOM entries. If pagination were
         * applied, only one data row from the second page would be present.
         */
        $this->assertCount(12, $rows);

        $this->assertStringContainsString(
            'Pagination test part 01',
            $content
        );

        $this->assertStringContainsString(
            'Pagination test part 11',
            $content
        );
    }

    public function testExportBOMPreservesCurrentSortOrder(): void
    {
        $project = $this->createProject(
            'Controller Sorted CSV Export Test'
        );

        $this->createBOMEntry(
            $project,
            'Lower quantity component',
            'U1',
            1.0,
            ''
        );

        $this->createBOMEntry(
            $project,
            'Higher quantity component',
            'U2',
            5.0,
            ''
        );

        $this->entityManager->flush();

        $request = $this->getDataTableRequest(
            [
                'quantity',
                'name',
            ],
            [
                'BOM Qty.',
                'Name',
            ]
        );

        /*
         * Quantity is column index 2 in ProjectBomEntriesDataTable.
         */
        $request['order'] = [
            [
                'column' => 2,
                'dir' => 'desc',
                'name' => '',
            ],
        ];

        $this->client->request(
            'POST',
            sprintf(
                '/en/project/%d/bom/export',
                $project->getID()
            ),
            $request
        );

        $response = $this->client->getResponse();

        $this->assertTrue(
            $response->isSuccessful(),
            sprintf(
                'The CSV export request failed with HTTP %d.',
                $response->getStatusCode()
            )
        );

        $content = $response->getContent();

        $this->assertNotFalse($content);

        $higherPosition = strpos(
            $content,
            'Higher quantity component'
        );

        $lowerPosition = strpos(
            $content,
            'Lower quantity component'
        );

        $this->assertNotFalse($higherPosition);
        $this->assertNotFalse($lowerPosition);

        $this->assertLessThan(
            $lowerPosition,
            $higherPosition,
            'The CSV did not preserve descending quantity order.'
        );
    }

    public function testExportBOMRequiresExportColumns(): void
    {
        $project = $this->createProject(
            'Controller Missing Columns CSV Export Test'
        );

        $this->createBOMEntry(
            $project,
            'Missing columns test entry',
            'U1',
            1.0,
            ''
        );

        $this->entityManager->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'No columns were specified for BOM export.'
        );

        $this->client->request(
            'POST',
            sprintf(
                '/en/project/%d/bom/export',
                $project->getID()
            ),
            [
                '_dt' => 'dt',
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'search' => [
                    'value' => '',
                    'regex' => false,
                ],
            ]
        );
    }

    #[DataProvider('projectBOMCsvProvider')]
    public function testProjectMetadataEditDoesNotRenderTheCompleteBOM(string $filename, int $expectedEntries): void
    {
        $project = $this->createProject(sprintf('Large project metadata edit test %d', $expectedEntries));
        $importer = self::getContainer()->get(BOMImporter::class);

        $entries = $importer->importFileIntoProject(
            new File(dirname(__DIR__) . '/assets/project_bom/issue_911/' . $filename),
            $project,
            ['type' => 'kicad_pcbnew']
        );

        $this->entityManager->flush();
        $this->assertCount($expectedEntries, $entries);

        $crawler = $this->client->request(
            'GET',
            sprintf('/en/project/%d/edit', $project->getID())
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $this->assertSame(
            0,
            $crawler->filter('[name^="project_admin_form[bom_entries]"]')->count(),
            'Project metadata editing must not render every BOM entry.'
        );

        $form = $crawler->filter('form[name="project_admin_form"]')->form([
            'project_admin_form[name]' => sprintf('Large project metadata edit test %d renamed', $expectedEntries),
        ]);
        $this->client->submit($form);

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $updatedProject = $this->entityManager->getRepository(Project::class)->find($project->getID());
        $this->assertInstanceOf(Project::class, $updatedProject);
        $this->assertSame(sprintf('Large project metadata edit test %d renamed', $expectedEntries), $updatedProject->getName());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function projectBOMCsvProvider(): iterable
    {
        yield '100 BOM entries' => ['Parts_0100.csv', 100];
        yield '200 BOM entries' => ['Parts_0200.csv', 200];
        yield '500 BOM entries' => ['Parts_0500.csv', 500];
        yield '1000 BOM entries' => ['Parts_1000.csv', 1000];
        yield '1400 BOM entries' => ['Parts_1400.csv', 1400];
        yield '1500 BOM entries' => ['Parts_1500.csv', 1500];
    }

    public function testBOMEntriesCanBeEditedSeparately(): void
    {
        $project = $this->createProject('Separate BOM edit test');
        $entry = $this->createBOMEntry($project, 'Original BOM entry', 'R1', 1.0, '');
        $this->entityManager->flush();

        $crawler = $this->client->request(
            'GET',
            sprintf('/en/project/%d/bom/%d/edit', $project->getID(), $entry->getID())
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $formCrawler = $crawler->filterXPath('//form[.//input[@name="project_bom_entry[name]"]]');
        $this->assertCount(1, $formCrawler);
        $form = $formCrawler->form();
        $nameField = $formCrawler->filter('input[name$="[name]"]')->first()->attr('name');
        $this->assertIsString($nameField);
        $form->setValues([
            $nameField => 'Changed BOM entry',
        ]);
        $this->client->submit($form);

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $updatedEntry = $this->entityManager->getRepository(ProjectBOMEntry::class)->find($entry->getID());
        $this->assertInstanceOf(ProjectBOMEntry::class, $updatedEntry);
        $this->assertSame('Changed BOM entry', $updatedEntry->getName());
    }

    public function testBOMEntriesCanBeDeletedSeparately(): void
    {
        $project = $this->createProject('Separate BOM delete test');
        $entry = $this->createBOMEntry($project, 'BOM entry to delete', 'R1', 1.0, '');
        $this->entityManager->flush();

        $crawler = $this->client->request(
            'GET',
            sprintf('/en/project/%d/bom/%d/edit', $project->getID(), $entry->getID())
        );

        $this->client->submit($crawler->filter('form')->last()->form());

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertSame(
            0,
            (int) $this->entityManager->createQuery(
                'SELECT COUNT(bom_entry.id) FROM App\\Entity\\ProjectSystem\\ProjectBOMEntry bom_entry WHERE bom_entry.id = :id'
            )->setParameter('id', $entry->getID())->getSingleScalarResult()
        );
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

    /**
     * Build the DataTables state sent by the browser.
     *
     * The table callback uses the configured server-side column definitions,
     * but order indexes refer to the complete ProjectBomEntriesDataTable.
     *
     * @param list<string> $exportColumns
     * @param list<string> $exportLabels
     *
     * @return array<string, mixed>
     */
    private function getDataTableRequest(
        array $exportColumns,
        array $exportLabels,
    ): array {
        return [
            '_dt' => 'dt',
            'draw' => 1,
            'start' => 0,
            'length' => 10,
            'columns' => [
                $this->getColumnRequest(
                    'picture',
                    false,
                    false
                ),
                $this->getColumnRequest(
                    'id',
                    true,
                    true
                ),
                $this->getColumnRequest(
                    'quantity',
                    true,
                    true
                ),
                $this->getColumnRequest(
                    'partId',
                    false,
                    true
                ),
                $this->getColumnRequest(
                    'name',
                    true,
                    true
                ),
                $this->getColumnRequest(
                    'ipn',
                    false,
                    true
                ),
                $this->getColumnRequest(
                    'description',
                    false,
                    false
                ),
                $this->getColumnRequest(
                    'category',
                    true,
                    true
                ),
                $this->getColumnRequest(
                    'footprint',
                    true,
                    true
                ),
                $this->getColumnRequest(
                    'manufacturer',
                    true,
                    true
                ),
                $this->getColumnRequest(
                    'manufacturing_status',
                    false,
                    true
                ),
                $this->getColumnRequest(
                    'mountnames',
                    true,
                    true
                ),
                $this->getColumnRequest(
                    'instockAmount',
                    false,
                    false
                ),
                $this->getColumnRequest(
                    'storelocation',
                    false,
                    true
                ),
                $this->getColumnRequest(
                    'price',
                    true,
                    true
                ),
                $this->getColumnRequest(
                    'ext_price',
                    false,
                    false
                ),
                $this->getColumnRequest(
                    'addedDate',
                    true,
                    true
                ),
                $this->getColumnRequest(
                    'lastModified',
                    true,
                    true
                ),
            ],
            'order' => [
                [
                    'column' => 4,
                    'dir' => 'asc',
                    'name' => '',
                ],
            ],
            'search' => [
                'value' => '',
                'regex' => false,
            ],
            'exportColumns' => $exportColumns,
            'exportLabels' => $exportLabels,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getColumnRequest(
        string $data,
        bool $searchable,
        bool $orderable,
    ): array {
        return [
            'data' => $data,
            'name' => '',
            'searchable' => $searchable,
            'orderable' => $orderable,
            'search' => [
                'value' => '',
                'regex' => false,
            ],
        ];
    }
}
