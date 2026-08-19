<?php

declare(strict_types=1);

namespace App\Tests\API\Endpoints;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\APITokenFixtures;
use App\Entity\Parameters\ParameterDefinition;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use App\Services\UserSystem\PermissionSchemaUpdater;
use App\Tests\API\AuthenticatedApiTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class ParameterDefinitionsEndpointTest extends AuthenticatedApiTestCase
{
    private const BASE_PATH = '/api/parameter_definitions';

    public function testGetCollection(): void
    {
        $client = $this->createDefinitionClient();
        $client->request('GET', self::BASE_PATH);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testCrudLifecycleAndChoiceCanonicalization(): void
    {
        $client = $this->createDefinitionClient();
        $response = $client->request('POST', self::BASE_PATH, [
            'json' => [
                'name' => 'API dielectric definition',
                'symbol' => 'D',
                'unit' => 'kind',
                'input_type' => ParameterDefinition::INPUT_TYPE_CHOICE,
                'choices' => ['X7R', ' x7r ', '', 'X5R'],
            ],
        ]);
        self::assertResponseIsSuccessful();
        $id = $response->toArray(true)['id'];
        self::assertIsInt($id);
        self::assertJsonContains([
            'name' => 'API dielectric definition',
            'input_type' => ParameterDefinition::INPUT_TYPE_CHOICE,
            'choices' => ['X7R', 'X5R'],
        ]);

        $client->request('GET', self::BASE_PATH.'/'.$id);
        self::assertResponseIsSuccessful();
        $client->request('PATCH', self::BASE_PATH.'/'.$id, [
            'json' => [
                'name' => 'API dielectric type',
                'choices' => ['C0G', 'NP0'],
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'name' => 'API dielectric type',
            'choices' => ['C0G', 'NP0'],
        ]);

        $client->request('DELETE', self::BASE_PATH.'/'.$id);
        self::assertResponseIsSuccessful();
    }

    public function testReadOnlyTokenCannotCreateDefinition(): void
    {
        $this->createDefinitionClient(APITokenFixtures::TOKEN_READONLY)->request('POST', self::BASE_PATH, [
            'json' => [
                'name' => 'Forbidden API definition',
                'input_type' => ParameterDefinition::INPUT_TYPE_TEXT,
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testOverlongChoiceReturnsValidationErrorInsteadOfServerError(): void
    {
        $client = $this->createDefinitionClient();
        $client->request('POST', self::BASE_PATH, [
            'json' => [
                'name' => 'API overlong choice definition',
                'input_type' => ParameterDefinition::INPUT_TYPE_CHOICE,
                'choices' => [str_repeat('X', ParameterDefinition::MAX_CHOICE_LENGTH + 1)],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            'violations' => [[
                'propertyPath' => 'choices',
                'message' => 'A parameter choice must not exceed 255 characters.',
            ]],
        ]);
    }

    public function testUsedDefinitionCannotBeDeletedThroughApi(): void
    {
        $client = $this->createDefinitionClient();
        $definition = (new ParameterDefinition())->setName('Used API definition');
        $parameter = (new PartParameter())->setDefinition($definition)->setValueText('value');
        $category = (new Category())->setName('Used API definition category');
        $part = (new Part())
            ->setName('Used API definition part')
            ->setCategory($category)
            ->addParameter($parameter);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($definition);
        $em->persist($category);
        $em->persist($part);
        $em->flush();
        $id = $definition->getID();
        self::assertNotNull($id);

        $client->request('DELETE', self::BASE_PATH.'/'.$id);

        self::assertResponseStatusCodeSame(409);
        self::assertJsonContains(['detail' => 'This parameter definition is still in use and cannot be deleted.']);
    }

    private function createDefinitionClient(string $token = APITokenFixtures::TOKEN_ADMIN): Client
    {
        $client = self::createAuthenticatedClient($token);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $admin = $em->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $admin);
        self::getContainer()->get(PermissionSchemaUpdater::class)->userUpgradeSchemaRecursively($admin);
        $em->flush();

        return $client;
    }
}
