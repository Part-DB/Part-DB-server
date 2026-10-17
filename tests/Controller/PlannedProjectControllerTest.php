<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\PlannedProject;
use App\Entity\ProjectSystem\PlannedProjectStatus;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PlannedProjectControllerTest extends WebTestCase
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

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        $this->assertInstanceOf(User::class, $user, 'The admin fixture user was not found.');

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

    /**
     * Creates a project with one part BOM entry per given [part name, needed quantity, stock] entry. A stock of null
     * means the part has no stock lot at all.
     * @param  array<int, array{0: string, 1: float, 2: float|null}>  $parts
     */
    private function createProject(string $name, array $parts): Project
    {
        $category = $this->entityManager->find(Category::class, 1);
        $this->assertInstanceOf(Category::class, $category);

        $project = new Project();
        $project->setName($name);
        $this->entityManager->persist($project);
        $this->entitiesToRemove[] = $project;

        foreach ($parts as [$part_name, $quantity, $stock]) {
            $part = (new Part())->setName($part_name)->setCategory($category);
            if (null !== $stock) {
                $lot = new PartLot();
                $lot->setAmount($stock);
                $part->addPartLot($lot);
            }
            $this->entityManager->persist($part);
            $this->entitiesToRemove[] = $part;

            $entry = new ProjectBOMEntry();
            $entry->setPart($part);
            $entry->setQuantity($quantity);
            //The first request of a test runs against this very entity manager, so the project has to know its entries
            $project->addBomEntry($entry);
            $this->entityManager->persist($entry);
            $this->entitiesToRemove[] = $entry;
        }

        $this->entityManager->flush();

        return $project;
    }

    /**
     * Plans the given project through the plan form (exactly like a user would), and returns the planned build.
     */
    private function planProject(Project $project, string $name): PlannedProject
    {
        $crawler = $this->client->request('GET', sprintf('/en/project/%d/plan?n=1', $project->getID()));
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $form = $crawler->filter('form[name="plan_project"]')->form();
        $form['plan_project[name]'] = $name;
        $this->client->submit($form);

        $this->assertTrue($this->client->getResponse()->isRedirect(), 'Planning should redirect to the new planned build');

        $planned_project = $this->entityManager->getRepository(PlannedProject::class)->findOneBy(['name' => $name]);
        $this->assertInstanceOf(PlannedProject::class, $planned_project);
        $this->entitiesToRemove[] = $planned_project;

        return $planned_project;
    }

    public function testIndexCanBeLoadedAndHandlesTheDataTableCallback(): void
    {
        $this->client->request('GET', '/en/planned_project');
        $this->assertTrue($this->client->getResponse()->isSuccessful());

        //The DataTable JS posts back to the same URL, so it must not be restricted to GET
        $this->client->request('POST', '/en/planned_project');
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    public function testPartWithoutLotsIsListedOnThePlannedBuild(): void
    {
        $project = $this->createProject('Planned build list test', [
            ['PBCT part without lots', 2.0, null],
            ['PBCT part with stock', 1.0, 10.0],
        ]);

        $planned_project = $this->planProject($project, 'PBCT plan');
        $this->assertSame(PlannedProjectStatus::PLANNED, $planned_project->getStatus());
        //Only the part with stock got something reserved
        $this->assertCount(1, $planned_project->getReservations());

        $this->client->request('GET', sprintf('/en/planned_project/%d', $planned_project->getID()));
        $this->assertTrue($this->client->getResponse()->isSuccessful());
        $content = (string) $this->client->getResponse()->getContent();

        //The part without any lot must be listed too, and the user is told why nothing is reserved for it
        $this->assertStringContainsString('PBCT part without lots', $content);
        $this->assertStringContainsString('PBCT part with stock', $content);
        $this->assertStringContainsString('No stock lot yet', $content);
        //It can not be built, as the part without lots has no stock
        $this->assertStringContainsString('The following parts are still missing', $content);
    }

    public function testPartsListOfThePlannedBuildCanBeSorted(): void
    {
        $project = $this->createProject('Planned build sort test', [
            ['PBST bravo', 5.0, 10.0],
            ['PBST alpha', 1.0, 20.0],
            ['PBST charlie', 3.0, null],
        ]);
        $planned_project = $this->planProject($project, 'PBST plan');

        //The order in which the parts show up in the parts table
        $positions = function (string $query) use ($planned_project): array {
            $crawler = $this->client->request('GET', sprintf('/en/planned_project/%d%s', $planned_project->getID(), $query));
            $this->assertTrue($this->client->getResponse()->isSuccessful());

            return $crawler->filter('table tbody tr td:first-child a')->each(
                static fn ($link): string => str_replace('PBST ', '', trim($link->text()))
            );
        };

        $this->assertSame(['alpha', 'bravo', 'charlie'], $positions(''));
        $this->assertSame(['alpha', 'bravo', 'charlie'], $positions('?sort=name&dir=asc'));
        $this->assertSame(['charlie', 'bravo', 'alpha'], $positions('?sort=name&dir=desc'));
        //Needed: alpha 1, charlie 3, bravo 5
        $this->assertSame(['alpha', 'charlie', 'bravo'], $positions('?sort=needed&dir=asc'));
        $this->assertSame(['bravo', 'charlie', 'alpha'], $positions('?sort=needed&dir=desc'));
        //Stock: charlie 0 (no lot), bravo 10, alpha 20
        $this->assertSame(['charlie', 'bravo', 'alpha'], $positions('?sort=amount&dir=asc'));
        //Invalid values must not break the page
        $this->assertSame(['alpha', 'bravo', 'charlie'], $positions('?sort=%22%3E%3Cscript%3E&dir=sideways'));

        //The column headers link to the sorting
        $crawler = $this->client->request('GET', sprintf('/en/planned_project/%d?sort=name&dir=asc', $planned_project->getID()));
        $this->assertGreaterThan(0, $crawler->filter('th a[href*="sort=needed"]')->count());
        //The currently sorted column links to the reversed order
        $this->assertGreaterThan(0, $crawler->filter('th a[href*="sort=name"][href*="dir=desc"]')->count());
    }

    public function testBuildingAPlannedBuildKeepsItAsBuiltAndListsTheUsedParts(): void
    {
        $project = $this->createProject('Planned build build test', [
            ['PBBT part', 2.0, 10.0],
        ]);
        $planned_project = $this->planProject($project, 'PBBT plan');
        $planned_project_id = $planned_project->getID();

        $crawler = $this->client->request('GET', sprintf('/en/planned_project/%d', $planned_project_id));
        $form = $crawler->filter('form[name="build_planned_project"]')->form();
        $this->client->submit($form);
        $this->assertTrue($this->client->getResponse()->isRedirect());

        $this->entityManager->clear();
        $planned_project = $this->entityManager->find(PlannedProject::class, $planned_project_id);
        $this->entitiesToRemove[] = $planned_project;

        //It is not deleted, but marked as built
        $this->assertInstanceOf(PlannedProject::class, $planned_project);
        $this->assertSame(PlannedProjectStatus::BUILT, $planned_project->getStatus());
        $this->assertCount(0, $planned_project->getReservations());

        $this->client->request('GET', sprintf('/en/planned_project/%d', $planned_project_id));
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('PBBT part', $content);

        //A built plan can not be built or cancelled again (e.g. by submitting a stale page)
        $this->client->request('POST', sprintf('/en/planned_project/%d/cancel', $planned_project_id));
        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->entityManager->clear();
        $this->assertSame(PlannedProjectStatus::BUILT, $this->entityManager->find(PlannedProject::class, $planned_project_id)->getStatus());
    }

    public function testPartWithActiveReservationsCanNotBeDeleted(): void
    {
        $project = $this->createProject('Planned build part delete test', [
            ['PBDT part', 1.0, 5.0],
        ]);
        $planned_project = $this->planProject($project, 'PBDT plan');

        $part = $this->entityManager->getRepository(Part::class)->findOneBy(['name' => 'PBDT part']);
        $this->assertInstanceOf(Part::class, $part);
        $this->assertTrue($part->hasReservations());
        $part_id = $part->getID();

        $crawler = $this->client->request('GET', sprintf('/en/part/%d/info', $part_id));
        $delete_form = $crawler->filter(sprintf('form[action$="/part/%d/delete"]', $part_id));
        $this->assertCount(1, $delete_form);
        $this->client->submit($delete_form->form());

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->find(Part::class, $part_id), 'The part must not have been deleted');
        $this->assertNotNull($this->entityManager->find(PlannedProject::class, $planned_project->getID()));
    }

    public function testReservedStockCanNotBeWithdrawnManually(): void
    {
        $project = $this->createProject('Planned build withdraw test', [
            ['PBWT part', 3.0, 5.0],
        ]);
        $this->planProject($project, 'PBWT plan');

        $part = $this->entityManager->getRepository(Part::class)->findOneBy(['name' => 'PBWT part']);
        $lot = $part->getPartLots()->first();
        $lot_id = $lot->getID();
        //3 of the 5 are reserved by the planned build
        $this->assertEqualsWithDelta(2.0, $lot->getAvailableAmount(), PHP_FLOAT_EPSILON);

        $crawler = $this->client->request('GET', sprintf('/en/part/%d/info', $part->getID()));
        $token = $crawler->filter('input[name="_csfr"]')->first()->attr('value');

        $withdraw = function (float $amount) use ($part, $lot_id, $token): void {
            $this->client->request('POST', sprintf('/en/part/%d/add_withdraw', $part->getID()), [
                '_csfr' => $token,
                'lot_id' => $lot_id,
                'amount' => $amount,
                'action' => 'withdraw',
                'comment' => '',
            ]);
            $this->assertTrue($this->client->getResponse()->isRedirect());
            $this->entityManager->clear();
        };

        //More than what is available: refused with a message instead of an error, the stock stays untouched
        $withdraw(4.0);
        $this->assertEqualsWithDelta(5.0, $this->entityManager->find(PartLot::class, $lot_id)->getAmount(), PHP_FLOAT_EPSILON);

        //Exactly the available amount works
        $withdraw(2.0);
        $this->assertEqualsWithDelta(3.0, $this->entityManager->find(PartLot::class, $lot_id)->getAmount(), PHP_FLOAT_EPSILON);

        //The reserved stock is still there
        $this->assertEqualsWithDelta(3.0, $this->entityManager->find(PartLot::class, $lot_id)->getReservedAmount(), PHP_FLOAT_EPSILON);
    }
}
