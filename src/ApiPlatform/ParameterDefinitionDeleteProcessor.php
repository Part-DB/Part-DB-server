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

namespace App\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parameters\ParameterDefinition;
use App\Repository\ParameterRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Prevents API clients from deleting a definition while parameters still use it.
 *
 * @implements ProcessorInterface<ParameterDefinition, void>
 */
final readonly class ParameterDefinitionDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        if (!$data instanceof ParameterDefinition) {
            throw new \InvalidArgumentException('Expected a parameter definition.');
        }

        $repository = $this->managerRegistry->getRepository(AbstractParameter::class);
        if (!$repository instanceof ParameterRepository) {
            throw new \LogicException('The abstract parameter repository is not configured correctly.');
        }

        if ($repository->countByDefinition($data) > 0) {
            throw new ConflictHttpException('This parameter definition is still in use and cannot be deleted.');
        }

        $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}
