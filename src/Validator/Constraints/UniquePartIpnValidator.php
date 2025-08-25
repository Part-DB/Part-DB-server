<?php

namespace App\Validator\Constraints;

use App\Entity\Parts\Part;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Doctrine\ORM\EntityManagerInterface;

class UniquePartIpnValidator extends ConstraintValidator
{
    private EntityManagerInterface $entityManager;
    private bool $enforceUniqueIpn;

    public function __construct(EntityManagerInterface $entityManager, bool $enforceUniqueIpn)
    {
        $this->entityManager = $entityManager;
        $this->enforceUniqueIpn = $enforceUniqueIpn;
    }

    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!$this->enforceUniqueIpn) {
            return;
        }

        /** @var Part $currentPart */
        $currentPart = $this->context->getObject();

        if (!$currentPart instanceof Part) {
            return;
        }

        $repository = $this->entityManager->getRepository(Part::class);
        $existingParts = $repository->findBy(['ipn' => $value]);

        foreach ($existingParts as $existingPart) {
            if ($currentPart->getId() !== $existingPart->getId()) {
                if ($this->enforceUniqueIpn) {
                    $this->context->buildViolation($constraint->message)
                        ->setParameter('{{ value }}', $value)
                        ->addViolation();
                }
            }
        }
    }
}