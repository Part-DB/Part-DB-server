<?php

declare(strict_types=1);

namespace App\Validator\Constraints\AssemblySystem;

use App\Entity\AssemblySystem\Assembly;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilder;
use ReflectionClass;

/**
 * Validator to check that no child assemblies are referenced in BOM entries.
 */
class AssemblyInvalidBomEntryValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof AssemblyInvalidBomEntry) {
            throw new UnexpectedTypeException($constraint, AssemblyInvalidBomEntry::class);
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

        foreach ($relevantEntries as $bomEntry) {
            $referencedAssembly = $bomEntry->getReferencedAssembly();

            if ($bomEntry->getAssembly()->getParent()?->getId() === $referencedAssembly->getParent()?->getId()) {
                //Save on the same assembly level
                continue;
            } elseif ($this->isInvalidBomEntry($referencedAssembly, $bomEntry->getAssembly())) {
                $this->addViolation($value, $constraint);
            }
        }
    }

    /**
     * Determines whether a Bill of Materials (BOM) entry is invalid based on the relationship
     * between the current assembly and the parent assembly.
     *
     * @param Assembly|null $currentAssembly The current assembly being analyzed. Null indicates no assembly is referenced.
     * @param Assembly      $parentAssembly The parent assembly to check against the current assembly.
     *
     * @return bool Returns
     */
    private function isInvalidBomEntry(?Assembly $currentAssembly, Assembly $parentAssembly): bool
    {
        //No assembly referenced -> no problems
        if ($currentAssembly === null) {
            return false;
        }

        //Check: is the current assembly a descendant of the parent assembly?
        if ($currentAssembly->isChildOf($parentAssembly)) {
            return true;
        }

        //Recursive check: Analyze the current assembly list
        foreach ($currentAssembly->getBomEntries() as $bomEntry) {
            $referencedAssembly = $bomEntry->getReferencedAssembly();

            if ($this->isInvalidBomEntry($referencedAssembly, $parentAssembly)) {
                return true;
            }
        }

        return false;

    }

    private function isOnSameLevel(Assembly $assembly1, Assembly $assembly2): bool
    {
        $parent1 = $assembly1->getParent();
        $parent2 = $assembly2->getParent();

        if ($parent1 === null || $parent2 === null) {
            return false;
        }

        // Beide Assemblies teilen denselben Parent
        return $parent1 !== null && $parent2 !== null && $parent1->getId() === $parent2->getId();
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