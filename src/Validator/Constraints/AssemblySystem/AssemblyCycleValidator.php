<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Validator\Constraints\AssemblySystem;

use App\Entity\AssemblySystem\Assembly;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;
use ReflectionClass;

/**
 * Validator class to check for cycles in assemblies based on BOM entries.
 *
 * This validator ensures that the structure of assemblies does not contain circular dependencies
 * by validating each entry in the Bill of Materials (BOM) of the given assembly. Additionally,
 * it can handle form-submitted BOM entries to include these in the validation process.
 */
class AssemblyCycleValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof AssemblyCycle) {
            throw new UnexpectedTypeException($constraint, AssemblyCycle::class);
        }

        if (!$value instanceof Assembly) {
            return;
        }

        $bomEntries = $value->getBomEntries()->toArray();

        // Consider additional entries from the form
        if ($this->context->getRoot() instanceof Form && $this->context->getRoot()->has('bom_entries')) {
            $formBomEntries = $this->context->getRoot()->get('bom_entries')->getData();
            if ($formBomEntries) {
                $given = is_array($formBomEntries) ? $formBomEntries : iterator_to_array($formBomEntries);
                foreach ($given as $givenIdx => $entry) {
                    if (in_array($entry, $bomEntries, true)) {
                        continue;
                    } else {
                        $bomEntries[$givenIdx] = $entry;
                    }
                }
            }
        }

        $visitedAssemblies = [];
        foreach ($bomEntries as  $bomEntry) {
            if ($this->hasCycle($bomEntry->getReferencedAssembly(), $value, $visitedAssemblies)) {
                $this->addViolation($value, $constraint);
            }
        }
    }

    private function hasCycle(?Assembly $currentAssembly, Assembly $originalAssembly, array &$visitedAssemblies): bool
    {
        if ($currentAssembly === null) {
            return false;
        }

        if (in_array($currentAssembly, $visitedAssemblies, true)) {
            return true;
        }

        $visitedAssemblies[] = $currentAssembly;

        foreach ($currentAssembly->getBomEntries() as $bomEntry) {
            if ($this->hasCycle($bomEntry->getReferencedAssembly(), $originalAssembly, $visitedAssemblies)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds a violation to the current context if it hasn’t already been added.
     *
     * This method checks whether a violation with the same property path as the current violation
     * already exists in the context. If such a violation is found, the current violation is not added again.
     * The process involves reflection to access private or protected properties of violation objects.
     *
     * @param mixed $value The value that triggered the violation.
     * @param Constraint $constraint The constraint containing the validation details.
     *
     */
    private function addViolation($value, Constraint $constraint): void
    {
        /** @var ConstraintViolationBuilder $buildViolation */
        $buildViolation = $this->context->buildViolation($constraint->message)
            ->setParameter('%name%', $value->getName());

        $alreadyAdded = false;

        try {
            $reflectionClass = new ReflectionClass($buildViolation);
            $property = $reflectionClass->getProperty('propertyPath');
            $propertyPath = $property->getValue($buildViolation);

            $availableViolations = $this->context->getViolations();

            foreach ($availableViolations as $tmpViolation) {
                $tmpReflectionClass = new ReflectionClass($tmpViolation);
                $tmpProperty = $tmpReflectionClass->getProperty('propertyPath');
                $tmpPropertyPath = $tmpProperty->getValue($tmpViolation);

                if ($tmpPropertyPath === $propertyPath) {
                    $alreadyAdded = true;
                }
            }
        } catch (\ReflectionException) {
        }

        if (!$alreadyAdded) {
            $buildViolation->addViolation();
        }
    }
}