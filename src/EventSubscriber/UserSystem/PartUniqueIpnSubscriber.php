<?php

namespace App\EventSubscriber\UserSystem;

use App\Entity\Parts\Part;
use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\EntityManagerInterface;

class PartUniqueIpnSubscriber implements EventSubscriber
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private readonly bool $enforceUniqueIpn = false
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
        ];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Part) {
            $this->ensureUniqueIpn($entity);
        }
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Part) {
            $this->ensureUniqueIpn($entity);
        }
    }

    private function ensureUniqueIpn(Part $part): void
    {
        if ($part->getIpn() === null || $part->getIpn() === '') {
            return;
        }

        $existingPart = $this->entityManager
            ->getRepository(Part::class)
            ->findOneBy(['ipn' => $part->getIpn()]);

        if ($existingPart && $existingPart->getId() !== $part->getId()) {
            if ($this->enforceUniqueIpn) {
                return;
            }

            // Anhang eines Inkrements bis ein einzigartiger Wert gefunden wird
            $increment = 1;
            $originalIpn = $part->getIpn();

            while ($this->entityManager
                ->getRepository(Part::class)
                ->findOneBy(['ipn' => $originalIpn . "_$increment"])) {
                $increment++;
            }

            $part->setIpn($originalIpn . "_$increment");
        }
    }
}
