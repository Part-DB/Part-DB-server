<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Tests\Controller\AdminPages;

use App\Entity\LabelSystem\LabelProcessMode;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\Entity\Parts\Category;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The /new page of the admin pages doubles as the list page, so it must stay readable with only the read permission,
 * while every way of actually creating elements from it must require the matching permission.
 * The "anonymous" fixture user only has read permissions, "user" can create elements but must not import them.
 */
#[Group('slow')]
#[Group('DB')]
final class AdminPageCreatePermissionTest extends WebTestCase
{
    public static function adminPageProvider(): \Iterator
    {
        yield ['/en/attachment_type'];
        yield ['/en/category'];
        yield ['/en/currency'];
        yield ['/en/footprint'];
        yield ['/en/label_profile'];
        yield ['/en/manufacturer'];
        yield ['/en/measurement_unit'];
        yield ['/en/part_custom_state'];
        yield ['/en/project'];
        yield ['/en/store_location'];
        yield ['/en/supplier'];
    }

    private function createUserClient(string $user): KernelBrowser
    {
        $client = static::createClient([], [
            'PHP_AUTH_USER' => $user,
            'PHP_AUTH_PW' => 'test',
        ]);
        $client->catchExceptions(false);

        return $client;
    }

    /**
     * Returns the CSRF tokens of the forms on the given page, indexed by the form name.
     * @return array<string, string>
     */
    private function getFormTokens(Crawler $crawler): array
    {
        $tokens = [];
        $crawler->filter('input[name$="[_token]"]')->each(function (Crawler $input) use (&$tokens): void {
            $name = (string) $input->attr('name');
            $tokens[substr($name, 0, (int) strpos($name, '['))] = (string) $input->attr('value');
        });

        return $tokens;
    }

    /**
     * Returns the name of the form used to edit the entity, which is always the first one on the page.
     */
    private function getEntityFormName(array $tokens): string
    {
        return array_key_first($tokens);
    }

    /**
     * Returns the fields required for the entity form to be valid. Without create permission only the fields that
     * are not disabled count, which are the label options for label profiles.
     * @return array<string, mixed>
     */
    private function getValidFormData(string $form): array
    {
        if ($form !== 'label_profile_admin_form') {
            return [];
        }

        return ['options' => [
            'width' => '50',
            'height' => '30',
            'supported_element' => 'part',
            'barcode_type' => 'none',
            'lines' => 'Label content',
        ]];
    }

    /**
     * Asserts that a category with the given name was created and removes it again. These pages render the tree of the
     * new element, and the rollback of the test transaction does not reach the tree cache. Removing the element
     * invalidates the cached tree, which would otherwise reference an ID that no longer exists in later tests.
     */
    private function assertCategoryCreatedAndRemoveIt(string $name): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $category = $em->getRepository(Category::class)->findOneBy(['name' => $name]);
        self::assertNotNull($category);

        $em->remove($category);
        $em->flush();
    }

    private function createJsonUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'partdb_import_test');
        file_put_contents($path, '[{"name":"Imported element"}]');

        return new UploadedFile($path, 'import.json', 'application/json', null, true);
    }

    #[DataProvider('adminPageProvider')]
    public function testListPageIsReadableWithoutCreatePermission(string $basePath): void
    {
        $client = $this->createUserClient('anonymous');

        $client->request('GET', $basePath.'/new');

        self::assertResponseIsSuccessful();
    }

    #[DataProvider('adminPageProvider')]
    public function testCreatingIsDeniedWithoutCreatePermission(string $basePath): void
    {
        $client = $this->createUserClient('anonymous');
        $tokens = $this->getFormTokens($client->request('GET', $basePath.'/new'));
        $form = $this->getEntityFormName($tokens);

        $this->expectException(AccessDeniedException::class);
        $client->request('POST', $basePath.'/new', [
            $form => ['name' => 'New element', '_token' => $tokens[$form]] + $this->getValidFormData($form),
        ]);
    }

    #[DataProvider('adminPageProvider')]
    public function testCloningIsDeniedWithoutCreatePermission(string $basePath): void
    {
        $client = $this->createUserClient('anonymous');
        $tokens = $this->getFormTokens($client->request('GET', $basePath.'/1/clone'));
        $form = $this->getEntityFormName($tokens);

        $this->expectException(AccessDeniedException::class);
        $client->request('POST', $basePath.'/1/clone', [
            $form => ['_token' => $tokens[$form]] + $this->getValidFormData($form),
        ]);
    }

    #[DataProvider('adminPageProvider')]
    public function testMassCreationIsDeniedWithoutCreatePermission(string $basePath): void
    {
        $client = $this->createUserClient('anonymous');
        $tokens = $this->getFormTokens($client->request('GET', $basePath.'/new'));

        $this->expectException(AccessDeniedException::class);
        $client->request('POST', $basePath.'/new', [
            'mass_creation_form' => ['lines' => 'New element', '_token' => $tokens['mass_creation_form']],
        ]);
    }

    #[DataProvider('adminPageProvider')]
    public function testImportIsDeniedWithoutImportPermission(string $basePath): void
    {
        $client = $this->createUserClient('anonymous');
        $tokens = $this->getFormTokens($client->request('GET', $basePath.'/new'));

        $this->expectException(AccessDeniedException::class);
        $client->request(
            'POST',
            $basePath.'/new',
            ['import' => ['format' => 'json', '_token' => $tokens['import']]],
            ['import' => ['file' => $this->createJsonUpload()]]
        );
    }

    public function testImportIsDeniedWithCreateButWithoutImportPermission(): void
    {
        $client = $this->createUserClient('user');
        $tokens = $this->getFormTokens($client->request('GET', '/en/category/new'));

        $this->expectException(AccessDeniedException::class);
        $client->request(
            'POST',
            '/en/category/new',
            ['import' => ['format' => 'json', '_token' => $tokens['import']]],
            ['import' => ['file' => $this->createJsonUpload()]]
        );
    }

    public function testCreatingWorksWithCreatePermission(): void
    {
        $client = $this->createUserClient('user');
        $tokens = $this->getFormTokens($client->request('GET', '/en/category/new'));
        $form = $this->getEntityFormName($tokens);

        $client->request('POST', '/en/category/new', [$form => ['name' => 'New element', '_token' => $tokens[$form]]]);

        self::assertResponseRedirects();
        $this->assertCategoryCreatedAndRemoveIt('New element');
    }

    public function testMassCreationWorksWithCreatePermission(): void
    {
        $client = $this->createUserClient('user');
        $tokens = $this->getFormTokens($client->request('GET', '/en/category/new'));

        $client->request('POST', '/en/category/new', [
            'mass_creation_form' => ['lines' => 'Mass created element', '_token' => $tokens['mass_creation_form']],
        ]);

        $this->assertCategoryCreatedAndRemoveIt('Mass created element');
    }

    public function testImportWorksWithImportPermission(): void
    {
        $client = $this->createUserClient('admin');
        $tokens = $this->getFormTokens($client->request('GET', '/en/category/new'));

        $client->request(
            'POST',
            '/en/category/new',
            ['import' => ['format' => 'json', 'csv_delimiter' => ';', '_token' => $tokens['import']]],
            ['import' => ['file' => $this->createJsonUpload()]]
        );

        $this->assertCategoryCreatedAndRemoveIt('Imported element');
    }

    /**
     * Cloning a twig mode profile must not allow changing its template without the use_twig permission,
     * the same way as editing it does not.
     */
    public function testCloningTwigLabelProfileDoesNotAllowChangingLinesWithoutTwigPermission(): void
    {
        $client = $this->createUserClient('user');
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $profile = new LabelProfile();
        $profile->setName('Twig profile');
        $profile->getOptions()->setSupportedElement(LabelSupportedElement::PART);
        $profile->getOptions()->setProcessMode(LabelProcessMode::TWIG);
        $profile->getOptions()->setLines('{{ element.name }}');
        $em->persist($profile);
        $em->flush();
        $url = '/en/label_profile/'.$profile->getID().'/clone';

        $tokens = $this->getFormTokens($client->request('GET', $url));
        $form = $this->getEntityFormName($tokens);

        $client->request('POST', $url, [$form => [
            '_token' => $tokens[$form],
            'name' => 'Cloned profile',
            'options' => [
                'width' => '50',
                'height' => '30',
                'supported_element' => 'part',
                'barcode_type' => 'none',
                'lines' => '{{ 7*7 }}',
            ],
        ]]);

        self::assertResponseRedirects();
        $em->clear();
        $clone = $em->getRepository(LabelProfile::class)->findOneBy(['name' => 'Cloned profile']);
        self::assertNotNull($clone);
        self::assertSame('{{ element.name }}', $clone->getOptions()->getLines());
    }
}
