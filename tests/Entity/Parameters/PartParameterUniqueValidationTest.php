<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace App\Tests\Entity\Parameters;

use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Form\ParameterType;
use App\Validator\Constraints\UniqueObjectCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Group('DB')]
#[Group('slow')]
final class PartParameterUniqueValidationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testRemovedParameterCanBeRecreatedWithTheSameNameBeforeFlush(): void
    {
        [$part, $oldParameter] = $this->createPersistedParameter('Same request parameter');
        $oldParameterId = $oldParameter->getID();

        $part->removeParameter($oldParameter);
        $newParameter = new PartParameter();
        $part->addParameter($newParameter);
        $form = $this->createParameterForm($newParameter);
        $form->submit($this->submission('Same request parameter'));

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertFalse($part->getParameters()->contains($oldParameter));
        self::assertTrue($part->getParameters()->contains($newParameter));
        self::assertCount(0, $this->validator()->validate($part));

        $this->entityManager()->flush();

        self::assertCount(1, $part->getParameters());
        self::assertNull($this->entityManager()->find(PartParameter::class, $oldParameterId));
        self::assertNotNull($newParameter->getID());
    }

    public function testTwoActiveParametersWithTheSameNameRemainInvalid(): void
    {
        [$part] = $this->createPersistedParameter('Active duplicate parameter');
        $duplicate = new PartParameter();
        $part->addParameter($duplicate);
        $form = $this->createParameterForm($duplicate);
        $form->submit($this->submission('Active duplicate parameter'));

        self::assertFalse($form->isValid(), 'UniqueEntity must reject a second active parameter.');

        $violations = $this->validator()->validate($part);
        $duplicateViolationFound = false;
        foreach ($violations as $violation) {
            if (UniqueObjectCollection::IS_NOT_UNIQUE === $violation->getCode()) {
                $duplicateViolationFound = true;
                break;
            }
        }

        self::assertTrue($duplicateViolationFound, 'The active collection duplicate protection must remain enabled.');
    }

    /** @return array{Part, PartParameter} */
    private function createPersistedParameter(string $parameterName): array
    {
        $category = new Category();
        $category->setName($parameterName.' category');
        $parameter = (new PartParameter())
            ->setName($parameterName)
            ->setGroup('Test group')
            ->setValueText('original');
        $part = new Part();
        $part->setName($parameterName.' part');
        $part->setCategory($category);
        $part->addParameter($parameter);

        $this->entityManager()->persist($category);
        $this->entityManager()->persist($part);
        $this->entityManager()->flush();

        return [$part, $parameter];
    }

    private function createParameterForm(PartParameter $parameter): FormInterface
    {
        return $this->formFactory()->create(ParameterType::class, $parameter, [
            'data_class' => PartParameter::class,
            'csrf_protection' => false,
        ]);
    }

    /** @return array<string, string> */
    private function submission(string $name): array
    {
        return [
            'name' => $name,
            'symbol' => '',
            'value_text' => 'replacement',
            'value_max' => '',
            'value_min' => '',
            'value_typical' => '',
            'unit' => '',
            'group' => 'Test group',
            'eda_visibility' => '',
            'eda_symbol_visibility' => '',
        ];
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function formFactory(): FormFactoryInterface
    {
        return self::getContainer()->get(FormFactoryInterface::class);
    }

    private function validator(): ValidatorInterface
    {
        return self::getContainer()->get(ValidatorInterface::class);
    }
}
