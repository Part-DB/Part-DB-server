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

namespace App\Services\Parameters;

use App\Entity\Parameters\ParameterDefinition;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Part;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class PendingParameterChoiceApplier
{
    public function __construct(private Security $security)
    {
    }

    public function apply(Part $part): void
    {
        foreach ($part->getParameters() as $parameter) {
            if (!$parameter instanceof PartParameter) {
                continue;
            }

            $pending_choice = $parameter->getPendingDefinitionChoice();
            if (null === $pending_choice) {
                continue;
            }

            $definition = $parameter->getDefinition();
            $pending_choice = trim($pending_choice);

            if (!$definition instanceof ParameterDefinition
                || ParameterDefinition::INPUT_TYPE_CHOICE !== $definition->getInputType()) {
                throw new LogicException('A pending choice requires a linked Choice parameter definition.');
            }
            if ('' === $pending_choice || mb_strlen($pending_choice) > ParameterDefinition::MAX_CHOICE_LENGTH) {
                throw new LogicException('The pending parameter choice is invalid.');
            }
            if ($pending_choice !== trim($parameter->getValueText())) {
                throw new LogicException('The pending choice does not match the parameter value.');
            }
            if (!$this->security->isGranted('edit', $definition)) {
                throw new AccessDeniedException('Editing this parameter definition is not allowed.');
            }

            $canonical_choice = $definition->addChoice($pending_choice);
            $parameter
                ->setValueText($canonical_choice)
                ->clearPendingDefinitionChoice();
        }
    }
}
