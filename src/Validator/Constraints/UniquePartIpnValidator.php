<?php

namespace App\Validator\Constraints;

use App\Entity\Parts\Part;
use App\Settings\MiscSettings\IpnSuggestSettings;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Doctrine\ORM\EntityManagerInterface;

class UniquePartIpnValidator extends ConstraintValidator
{
    private EntityManagerInterface $entityManager;
    private IpnSuggestSettings $ipnSuggestSettings;

    public function __construct(EntityManagerInterface $entityManager, IpnSuggestSettings $ipnSuggestSettings)
    {
        $this->entityManager = $entityManager;
        $this->ipnSuggestSettings = $ipnSuggestSettings;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        //If the autoAppendSuffix option is enabled, the IPN becomes unique automatically later
        if ($this->ipnSuggestSettings->autoAppendSuffix) {
            return;
        }

        if (!$constraint instanceof UniquePartIpnConstraint) {
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
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $value)
                    ->addViolation();
            }
        }
    }
}
