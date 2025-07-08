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

        $availableViolations = $this->context->getViolations();
        if (count($availableViolations) > 0) {
            //already violations given, currently no more needed to check

            return;
        }

        $bomEntries = [];

        if ($this->context->getRoot() instanceof Form && $this->context->getRoot()->has('bom_entries')) {
            $bomEntries = $this->context->getRoot()->get('bom_entries')->getData();
            $bomEntries = is_array($bomEntries) ? $bomEntries : iterator_to_array($bomEntries);
        } elseif ($this->context->getRoot() instanceof Assembly) {
            $bomEntries = $value->getBomEntries()->toArray();
        }

        $relevantEntries = [];

        foreach ($bomEntries as $bomEntry) {
            if ($bomEntry->getReferencedAssembly() !== null) {
                $relevantEntries[$bomEntry->getId()] = $bomEntry;
            }
        }

        $visitedAssemblies = [];
        foreach ($relevantEntries as $bomEntry) {
            if ($this->hasCycle($bomEntry->getReferencedAssembly(), $value, $visitedAssemblies)) {
                $this->addViolation($value, $constraint);
            }
        }
    }

    /**
     * Determines if there is a cyclic dependency in the assembly hierarchy.
     *
     * This method checks if a cycle exists in the hierarchy of referenced assemblies starting
     * from a given assembly. It traverses through the Bill of Materials (BOM) entries of each
     * assembly recursively and keeps track of visited assemblies to detect cycles.
     *
     * @param Assembly|null $currentAssembly The current assembly being checked for cycles.
     * @param Assembly      $originalAssembly The original assembly from where the cycle detection started.
     * @param Assembly[]    $visitedAssemblies A list of assemblies that have been visited during the current traversal.
     *
     * @return bool True if a cycle is detected, false otherwise.
     */
    private function hasCycle(?Assembly $currentAssembly, Assembly $originalAssembly, array $visitedAssemblies = []): bool
    {
        //No referenced assembly → no cycle
        if ($currentAssembly === null) {
            return false;
        }

        //If the assembly has already been visited, there is a cycle
        if (in_array($currentAssembly->getId(), array_map(fn($a) => $a->getId(), $visitedAssemblies), true)) {
            return true;
        }

        //Add the current assembly to the visited
        $visitedAssemblies[] = $currentAssembly;

        //Go through the bom entries of the current assembly
        foreach ($currentAssembly->getBomEntries() as $bomEntry) {
            $referencedAssembly = $bomEntry->getReferencedAssembly();

            if ($referencedAssembly !== null && $this->hasCycle($referencedAssembly, $originalAssembly, $visitedAssemblies)) {
                return true;
            }
        }

        //Remove the current assembly from the list of visit (recursion completed)
        array_pop($visitedAssemblies);

        return false;
    }

    /**
     * Adds a violation to the current context if it hasn’t already been added.
     *
     * This method checks whether a violation with the same property path as the current violation
     * already exists in the context. If such a violation is found, the current violation is not added again.
     * The process involves reflection to access private or protected properties of violation objects.
     *
     * @param mixed         $value The value that triggered the violation.
     * @param Constraint    $constraint The constraint containing the validation details.
     *
     */
    private function addViolation(mixed $value, Constraint $constraint): void
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
