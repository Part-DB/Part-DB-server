<?php

declare(strict_types=1);

namespace App\Services\ImportExportSystem;

use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class ImporterResult
{
    private array $bomEntries = [];
    private ConstraintViolationList $violations;

    public function __construct(array $bomEntries = [])
    {
        $this->bomEntries = $bomEntries;
        $this->violations = new ConstraintViolationList();
    }

    /**
     * Fügt einen neuen BOM-Eintrag hinzu.
     */
    public function addBomEntry(object $bomEntry): void
    {
        $this->bomEntries[] = $bomEntry;
    }

    /**
     * Gibt alle BOM-Einträge zurück.
     */
    public function getBomEntries(): array
    {
        return $this->bomEntries;
    }

    /**
     * Gibt die Liste der Violation zurück.
     */
    public function getViolations(): ConstraintViolationList
    {
        return $this->violations;
    }

    /**
     * Fügt eine neue `ConstraintViolation` zur Liste hinzu.
     */
    public function addViolation(ConstraintViolation $violation): void
    {
        $this->violations->add($violation);
    }

    /**
     * Prüft, ob die Liste der Violationen leer ist.
     */
    public function hasViolations(): bool
    {
        return count($this->violations) > 0;
    }
}