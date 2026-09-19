<?php

declare(strict_types=1);

namespace App\Tests\Controller\AdminPages;

use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\Parameters\ParameterDefinition;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Repository\LogEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

#[Group('DB')]
#[Group('slow')]
final class ParameterDefinitionControllerTest extends WebTestCase
{
    public static function readAccessProvider(): iterable
    {
        yield 'no read permission' => ['noread', false];
        yield 'read-only user' => ['anonymous', true];
        yield 'part editor' => ['user', true];
        yield 'administrator' => ['admin', true];
    }

    #[DataProvider('readAccessProvider')]
    public function testAdminReadAccessUsesDedicatedPermissions(string $username, bool $allowed): void
    {
        $client = $this->createAuthenticatedClient($username);
        $client->request('GET', '/en/parameter_definition/new');

        self::assertSame($allowed, $client->getResponse()->isSuccessful());
        self::assertSame(!$allowed, $client->getResponse()->isForbidden());
    }

    public function testPartEditorCanReadButCannotManageDefinitions(): void
    {
        $client = $this->createAuthenticatedClient('user');
        $crawler = $client->request('GET', '/en/parameter_definition/new');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('#parameter_definition_admin_form_name[disabled]')->count());
        self::assertSame(1, $crawler->filter('#parameter_definition_admin_form_save[disabled]')->count());
    }

    public function testToolsTreeContainsParameterDefinitionsEntry(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/en/tree/tools');

        self::assertResponseIsSuccessful();
        $tree = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertStringContainsString('/en/parameter_definition/new', json_encode($tree, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function testCreateTextDefinitionClearsSubmittedChoices(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/en/parameter_definition/new');
        self::assertResponseIsSuccessful();
        self::assertSame(0, $crawler->filter('a[href="#attachments"]')->count());

        $crawler = $this->submitDefinitionForm($client, $crawler, [
            'name' => 'Controller text definition',
            'input_type' => ParameterDefinition::INPUT_TYPE_TEXT,
            'choices_text' => "Ignored\nvalues",
        ]);

        self::assertTrue(
            $client->getResponse()->isRedirect(),
            sprintf(
                'HTTP %d: %s',
                $client->getResponse()->getStatusCode(),
                implode(' | ', $crawler->filter('.invalid-feedback, form[name="parameter_definition_admin_form"] li')->each(static fn (Crawler $node): string => $node->text())),
            ),
        );
        $definition = $this->findDefinition('Controller text definition');
        self::assertSame(ParameterDefinition::INPUT_TYPE_TEXT, $definition->getInputType());
        self::assertSame([], $definition->getChoices());
    }

    public function testCreateChoiceDefinitionCanonicalizesChoices(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/en/parameter_definition/new');

        $this->submitDefinitionForm($client, $crawler, [
            'name' => 'Controller choice definition',
            'symbol' => 'D',
            'unit' => 'kind',
            'input_type' => ParameterDefinition::INPUT_TYPE_CHOICE,
            'choices_text' => " X7R \nx7r\n\nX7r\nX5R ",
        ]);

        self::assertResponseRedirects();
        $definition = $this->findDefinition('Controller choice definition');
        self::assertSame(['X7R', 'X5R'], $definition->getChoices());
        self::assertSame('D', $definition->getSymbol());
        self::assertSame('kind', $definition->getUnit());
    }

    public function testPersistedDefinitionAppearsImmediatelyInAdminTree(): void
    {
        $client = $this->createAuthenticatedClient();

        // Opening the creation page primes the generic tree cache before the definition exists.
        $crawler = $client->request('GET', '/en/parameter_definition/new');
        self::assertResponseIsSuccessful();

        $this->submitDefinitionForm($client, $crawler, [
            'name' => 'Visible admin tree definition',
            'input_type' => ParameterDefinition::INPUT_TYPE_CHOICE,
            'choices_text' => "C0G\nX7R",
        ]);
        self::assertResponseRedirects();

        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        $tree_data = $crawler->filter('[data-controller="elements--tree"]')->attr('data-tree-data');
        self::assertNotNull($tree_data);
        $tree = json_decode($tree_data, true, flags: JSON_THROW_ON_ERROR);

        self::assertStringContainsString(
            'Visible admin tree definition',
            json_encode($tree, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    public function testEditDefinitionCreatesHistoryEntry(): void
    {
        $client = $this->createAuthenticatedClient();
        $definition = (new ParameterDefinition())
            ->setName('Controller history definition')
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X7R']);
        $em = $this->entityManager();
        $em->persist($definition);
        $em->flush();
        $id = $definition->getID();
        self::assertNotNull($id);

        $crawler = $client->request('GET', '/en/parameter_definition/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $crawler->filter('a[href="#history"]')->count());

        $this->submitDefinitionForm($client, $crawler, [
            'name' => 'Controller renamed definition',
            'input_type' => ParameterDefinition::INPUT_TYPE_CHOICE,
            'choices_text' => "C0G\nX7R\nX5R",
        ]);
        self::assertResponseIsSuccessful();

        $em->clear();
        $reloaded = $em->find(ParameterDefinition::class, $id);
        self::assertInstanceOf(ParameterDefinition::class, $reloaded);
        self::assertSame('Controller renamed definition', $reloaded->getName());
        self::assertSame(['C0G', 'X7R', 'X5R'], $reloaded->getChoices());

        $log_repository = $em->getRepository(AbstractLogEntry::class);
        self::assertInstanceOf(LogEntryRepository::class, $log_repository);
        self::assertNotEmpty(array_filter(
            $log_repository->getElementHistory($reloaded),
            static fn (AbstractLogEntry $entry): bool => $entry instanceof ElementEditedLogEntry,
        ));
    }

    public function testRemovingUsedChoiceThroughAdminDeprecatesItAndShowsReadOnlyList(): void
    {
        $client = $this->createAuthenticatedClient();
        $definition = (new ParameterDefinition())
            ->setName('Controller retired dielectric')
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X7R', 'X5R']);
        $parameter = (new PartParameter())->setDefinition($definition)->setValueText('X7R');
        $category = (new Category())->setName('Controller retired category');
        $part = (new Part())
            ->setName('Controller retired part')
            ->setCategory($category)
            ->addParameter($parameter);
        $em = $this->entityManager();
        $em->persist($definition);
        $em->persist($category);
        $em->persist($part);
        $em->flush();
        $definition_id = $definition->getID();
        $parameter_id = $parameter->getID();
        self::assertNotNull($definition_id);
        self::assertNotNull($parameter_id);

        $crawler = $client->request('GET', '/en/parameter_definition/'.$definition_id.'/edit');
        self::assertResponseIsSuccessful();
        $crawler = $this->submitDefinitionForm($client, $crawler, [
            'name' => 'Controller retired dielectric',
            'input_type' => ParameterDefinition::INPUT_TYPE_CHOICE,
            'choices_text' => "C0G\nX5R",
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Deprecated choices', $crawler->filter('body')->text());
        self::assertStringContainsString('X7R', $crawler->filter('body')->text());
        $em->clear();
        $reloaded_definition = $em->find(ParameterDefinition::class, $definition_id);
        $reloaded_parameter = $em->find(PartParameter::class, $parameter_id);
        self::assertInstanceOf(ParameterDefinition::class, $reloaded_definition);
        self::assertInstanceOf(PartParameter::class, $reloaded_parameter);
        self::assertSame(['C0G', 'X5R'], $reloaded_definition->getChoices());
        self::assertSame(['X7R'], $reloaded_definition->getDeprecatedChoices());
        self::assertSame('X7R', $reloaded_parameter->getValueText());
    }

    public function testCaseInsensitiveDuplicateNameIsAFormError(): void
    {
        $client = $this->createAuthenticatedClient();
        $em = $this->entityManager();
        $em->persist((new ParameterDefinition())->setName('Controller Unique Name'));
        $em->flush();

        $crawler = $client->request('GET', '/en/parameter_definition/new');
        $crawler = $this->submitDefinitionForm($client, $crawler, [
            'name' => 'controller unique name',
            'input_type' => ParameterDefinition::INPUT_TYPE_TEXT,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString(
            'A parameter definition with this name already exists.',
            $crawler->filter('body')->text(),
        );
    }

    public function testUnusedDefinitionCanBeDeleted(): void
    {
        $client = $this->createAuthenticatedClient();
        $definition = (new ParameterDefinition())->setName('Controller unused deletion');
        $em = $this->entityManager();
        $em->persist($definition);
        $em->flush();
        $id = $definition->getID();
        self::assertNotNull($id);

        $this->deleteDefinition($client, $id);

        self::assertResponseRedirects('/en/parameter_definition/new');
        $em->clear();
        self::assertNull($em->find(ParameterDefinition::class, $id));
    }

    public function testUsedDefinitionDeletionIsRefusedCleanly(): void
    {
        $client = $this->createAuthenticatedClient();
        $definition = (new ParameterDefinition())->setName('Controller used deletion');
        $parameter = (new PartParameter())->setDefinition($definition)->setValueText('value');
        $category = (new Category())->setName('Controller deletion category');
        $part = (new Part())
            ->setName('Controller deletion part')
            ->setCategory($category)
            ->addParameter($parameter);
        $em = $this->entityManager();
        $em->persist($definition);
        $em->persist($category);
        $em->persist($part);
        $em->flush();
        $id = $definition->getID();
        self::assertNotNull($id);

        $this->deleteDefinition($client, $id);

        self::assertResponseRedirects('/en/parameter_definition/'.$id.'/edit');
        $crawler = $client->followRedirect();
        self::assertStringContainsString(
            'This parameter definition is still in use and cannot be deleted.',
            $crawler->filter('body')->text(),
        );
        $em->clear();
        self::assertInstanceOf(ParameterDefinition::class, $em->find(ParameterDefinition::class, $id));
    }

    private function createAuthenticatedClient(string $username = 'admin'): KernelBrowser
    {
        return static::createClient([], [
            'PHP_AUTH_USER' => $username,
            'PHP_AUTH_PW' => 'test',
        ]);
    }

    /** @param array<string, string> $values */
    private function submitDefinitionForm(KernelBrowser $client, Crawler $crawler, array $values): Crawler
    {
        $form = $crawler->filter('form[name="parameter_definition_admin_form"]')->form();
        $submitted = [];
        foreach ($values as $field => $value) {
            $submitted['parameter_definition_admin_form['.$field.']'] = $value;
        }

        return $client->submit($form, $submitted);
    }

    private function deleteDefinition(KernelBrowser $client, int $id): void
    {
        $crawler = $client->request('GET', '/en/parameter_definition/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        $delete_form = $crawler->filter('form[action="/en/parameter_definition/'.$id.'"]')->form();
        $client->submit($delete_form);
    }

    private function findDefinition(string $name): ParameterDefinition
    {
        $definition = $this->entityManager()->getRepository(ParameterDefinition::class)
            ->findOneBy(['normalized_name' => mb_strtolower($name)]);
        self::assertInstanceOf(ParameterDefinition::class, $definition);

        return $definition;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
