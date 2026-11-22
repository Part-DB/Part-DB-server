<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Form\Attachments;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Form\AttachmentFormType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

#[Group("slow")]
#[Group("DB")]
final class AttachmentFormTypeTest extends KernelTestCase
{
    private function submitForm(string $url): FormInterface
    {
        self::bootKernel();
        $formFactory = static::getContainer()->get(FormFactoryInterface::class);
        $attachmentType = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(AttachmentType::class)->find(1);

        $form = $formFactory->create(AttachmentFormType::class, new PartAttachment(), [
            'csrf_protection' => false,
        ]);
        $form->submit([
            'name' => 'Test',
            'attachment_type' => (string) $attachmentType->getID(),
            'url' => $url,
        ]);

        return $form;
    }

    public static function invalidURLDataProvider(): \Iterator
    {
        yield ['javascript:alert(document.cookie)//https://example.com/x.pdf', 'Only HTTP and HTTPS URLs are allowed for external attachments.'];
        yield ['www.foo.bar', 'This value is not a valid URL.'];
    }

    #[DataProvider('invalidURLDataProvider')]
    public function testURLViolationsAreMappedToURLField(string $url, string $expectedMessage): void
    {
        $form = $this->submitForm($url);

        $this->assertFalse($form->isValid());
        //The error must be shown on the url field, not on the form itself
        $this->assertCount(0, $form->getErrors(), (string) $form->getErrors());
        $urlErrors = $form->get('url')->getErrors();
        $messages = array_map(static fn($error) => $error->getMessage(), iterator_to_array($urlErrors));
        $this->assertContains($expectedMessage, $messages);
    }

    public static function validURLDataProvider(): \Iterator
    {
        yield ['https://example.com/test.pdf'];
        yield ['%FOOTPRINTS%/Active/ICs/IC_DFS.png'];
    }

    #[DataProvider('validURLDataProvider')]
    public function testValidURLs(string $url): void
    {
        $form = $this->submitForm($url);

        $this->assertCount(0, $form->get('url')->getErrors(true), (string) $form->get('url')->getErrors(true));
    }
}
