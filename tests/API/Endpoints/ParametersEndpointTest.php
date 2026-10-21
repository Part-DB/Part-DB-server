<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
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

declare(strict_types=1);


namespace App\Tests\API\Endpoints;

use App\Entity\Parameters\ParameterDefinition;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use App\Services\UserSystem\PermissionSchemaUpdater;
use ApiPlatform\Symfony\Bundle\Test\Client;
use Doctrine\ORM\EntityManagerInterface;

final class ParametersEndpointTest extends CrudEndpointTestCase
{

    protected function getBasePath(): string
    {
        return '/api/parameters';
    }

    public function testElementLifecycle(): void
    {
        //Type should be automatically guessed from the element
        $this->_testPostItem([
            'name' => 'test',
            'element' => '/api/parts/1',
        ]);

        //Or manually set
        $response = $this->_testPostItem([
            'name' => 'test',
            'element' => '/api/footprints/1',
            '_type' => 'Footprint'
        ]);

        $id = $this->getIdOfCreatedElement($response);

        //Check if the new item is in the database
        $this->_testGetItem($id);

        //Check if we can change the item
        $this->_testPatchItem($id, [
            'name' => 'test2',
        ]);

        //Check if we can delete the item
        $this->_testDeleteItem($id);
    }

    public function testEffectiveDefinitionMetadataIsExposedWithoutDuplicatedWritableFields(): void
    {
        $client = self::createAuthenticatedClient();
        $definition = (new ParameterDefinition())
            ->setName('API parameter dielectric')
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['C0G', 'X7R']);
        $entity_manager = self::getContainer()->get(EntityManagerInterface::class);
        $admin = $entity_manager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $admin);
        self::getContainer()->get(PermissionSchemaUpdater::class)->userUpgradeSchemaRecursively($admin);
        $entity_manager->flush();
        $entity_manager->persist($definition);
        $entity_manager->flush();
        $definition_id = $definition->getID();
        self::assertNotNull($definition_id);

        $response = $client->request('POST', $this->getBasePath(), [
            'json' => [
                'name' => 'API parameter dielectric',
                'element' => '/api/parts/1',
                'definition' => '/api/parameter_definitions/'.$definition_id,
                'value_text' => 'X7R',
            ],
        ]);
        self::assertResponseIsSuccessful();

        self::assertJsonContains([
            'definition' => '/api/parameter_definitions/'.$definition_id,
            'input_type' => ParameterDefinition::INPUT_TYPE_CHOICE,
            'choices' => ['C0G', 'X7R'],
            'value_text' => 'X7R',
        ]);
    }

    public function testParameterWithoutDefinitionRemainsFreeText(): void
    {
        $response = $this->_testPostItem([
            'name' => 'API free text parameter',
            'element' => '/api/parts/1',
            'value_text' => 'arbitrary value',
        ]);

        self::assertJsonContains([
            'input_type' => ParameterDefinition::INPUT_TYPE_TEXT,
            'choices' => [],
            'value_text' => 'arbitrary value',
        ]);
    }

    /** CHOICE-DEPRECATION-016 */
    public function testApiCreateRejectsDeprecatedChoice(): void
    {
        [$client, $definition] = $this->createDeprecatedChoiceFixture();

        $response = $client->request('POST', $this->getBasePath(), [
            'json' => [
                'name' => 'API rejected deprecated create',
                'element' => '/api/parts/1',
                'definition' => '/api/parameter_definitions/'.$definition->getID(),
                'value_text' => 'X7R',
            ],
        ]);

        self::assertContains($response->getStatusCode(), [400, 422]);
    }

    public function testApiCreateAcceptsNotDefinedChoice(): void
    {
        [$client, $definition] = $this->createDeprecatedChoiceFixture();

        $response = $client->request('POST', $this->getBasePath(), [
            'json' => [
                'name' => 'API empty choice create',
                'element' => '/api/parts/1',
                'definition' => '/api/parameter_definitions/'.$definition->getID(),
                'value_text' => '',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['value_text' => '']);
    }

    /** CHOICE-DEPRECATION-017 */
    public function testApiUpdateRejectsAssignmentOfDeprecatedChoice(): void
    {
        [$client, $definition] = $this->createDeprecatedChoiceFixture();
        $parameter = $this->persistParameter($definition, 'X5R');

        $response = $client->request('PATCH', $this->getItemPath($parameter->getID()), [
            'json' => ['value_text' => 'X7R'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertContains($response->getStatusCode(), [400, 422]);
    }

    public function testApiUpdateRejectsUnknownChoice(): void
    {
        [$client, $definition] = $this->createDeprecatedChoiceFixture();
        $parameter = $this->persistParameter($definition, 'X5R');

        $response = $client->request('PATCH', $this->getItemPath($parameter->getID()), [
            'json' => ['value_text' => 'Y5V'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertContains($response->getStatusCode(), [400, 422]);
    }

    public function testApiUpdateCannotReuseDeprecatedChoiceFromAnotherDefinition(): void
    {
        [$client, $first_definition] = $this->createDeprecatedChoiceFixture(' first');
        $second_definition = (new ParameterDefinition())
            ->setName('API deprecated assignment second')
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['X5R'])
            ->setDeprecatedChoices(['X7R']);
        $entity_manager = self::getContainer()->get(EntityManagerInterface::class);
        $entity_manager->persist($second_definition);
        $entity_manager->flush();
        $parameter = $this->persistParameter($first_definition, 'X5R');

        $response = $client->request('PATCH', $this->getItemPath($parameter->getID()), [
            'json' => [
                'definition' => '/api/parameter_definitions/'.$second_definition->getID(),
                'value_text' => 'X7R',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertContains($response->getStatusCode(), [400, 422]);
    }

    /** CHOICE-DEPRECATION-018 */
    public function testApiUpdatePreservesSameDeprecatedChoice(): void
    {
        [$client, $definition] = $this->createDeprecatedChoiceFixture();
        $parameter = $this->persistParameter($definition, 'X7R');

        $response = $client->request('PATCH', $this->getItemPath($parameter->getID()), [
            'json' => ['value_text' => 'X7R'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['value_text' => 'X7R']);
    }

    /** CHOICE-DEPRECATION-019 */
    public function testApiUpdateChangesDeprecatedChoiceToActiveChoice(): void
    {
        [$client, $definition] = $this->createDeprecatedChoiceFixture();
        $parameter = $this->persistParameter($definition, 'X7R');

        $response = $client->request('PATCH', $this->getItemPath($parameter->getID()), [
            'json' => ['value_text' => 'X5R'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['value_text' => 'X5R']);
    }

    /** CHOICE-DEPRECATION-020 */
    public function testApiUpdateChangesDeprecatedChoiceToNotDefined(): void
    {
        [$client, $definition] = $this->createDeprecatedChoiceFixture();
        $parameter = $this->persistParameter($definition, 'X7R');

        $response = $client->request('PATCH', $this->getItemPath($parameter->getID()), [
            'json' => ['value_text' => ''],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['value_text' => '']);
    }

    /** @return array{Client, ParameterDefinition} */
    private function createDeprecatedChoiceFixture(string $suffix = ''): array
    {
        $client = self::createAuthenticatedClient();
        $definition = (new ParameterDefinition())
            ->setName('API deprecated assignment'.$suffix)
            ->setInputType(ParameterDefinition::INPUT_TYPE_CHOICE)
            ->setChoices(['X5R'])
            ->setDeprecatedChoices(['X7R']);
        $entity_manager = self::getContainer()->get(EntityManagerInterface::class);
        $entity_manager->persist($definition);
        $entity_manager->flush();

        return [$client, $definition];
    }

    private function persistParameter(ParameterDefinition $definition, string $value): PartParameter
    {
        $entity_manager = self::getContainer()->get(EntityManagerInterface::class);
        $part = $entity_manager->find(Part::class, 1);
        self::assertInstanceOf(Part::class, $part);
        $parameter = (new PartParameter())
            ->setName('API deprecated parameter')
            ->setElement($part)
            ->setDefinition($definition)
            ->setValueText($value);
        $entity_manager->persist($parameter);
        $entity_manager->flush();
        $parameter_id = $parameter->getID();
        self::assertNotNull($parameter_id);
        $entity_manager->clear();

        $reloaded_parameter = $entity_manager->find(PartParameter::class, $parameter_id);
        self::assertInstanceOf(PartParameter::class, $reloaded_parameter);

        return $reloaded_parameter;
    }
}
