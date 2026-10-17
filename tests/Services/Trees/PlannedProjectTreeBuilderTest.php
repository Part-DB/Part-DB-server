<?php

declare(strict_types=1);

namespace App\Tests\Services\Trees;

use App\Entity\ProjectSystem\PlannedProject;
use App\Entity\ProjectSystem\PlannedProjectStatus;
use App\Services\Trees\PlannedProjectTreeBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PlannedProjectTreeBuilderTest extends TestCase
{
    private function createBuilder(bool $granted, array $plannedProjects): PlannedProjectTreeBuilder
    {
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findBy')->willReturnCallback(function (array $criteria) use ($plannedProjects): array {
            //Only plans that are still planned are shown in the tree
            $this->assertSame(['status' => PlannedProjectStatus::PLANNED], $criteria);

            return $plannedProjects;
        });
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => '/' . $route . ($parameters !== [] ? '/' . $parameters['id'] : '')
        );

        return new PlannedProjectTreeBuilder($translator, $urlGenerator, $security, $entityManager);
    }

    public function testNothingIsShownWithoutPermission(): void
    {
        $this->assertSame([], $this->createBuilder(false, [new PlannedProject()])->getTree());
    }

    public function testGroupNodeLinksToTheListAndContainsAllPlannedBuilds(): void
    {
        $plan = new PlannedProject();
        $plan->setName('My plan');
        $reflection = new \ReflectionProperty($plan, 'id');
        $reflection->setValue($plan, 7);

        $tree = $this->createBuilder(true, [$plan])->getTree();

        $this->assertCount(1, $tree);
        $group = $tree[0];
        $this->assertSame('planned_build.labelp', $group->getText());
        //The group itself is clickable and opens the list of all planned builds
        $this->assertSame('/planned_project_index', $group->getHref());

        $children = $group->getNodes();
        $this->assertCount(1, $children);
        $this->assertSame('My plan', $children[0]->getText());
        $this->assertSame('/planned_project_info/7', $children[0]->getHref());
    }

    public function testGroupNodeIsStillShownWithoutAnyPlannedBuild(): void
    {
        $tree = $this->createBuilder(true, [])->getTree();

        $this->assertCount(1, $tree);
        $this->assertSame([], $tree[0]->getNodes() ?? []);
    }
}
