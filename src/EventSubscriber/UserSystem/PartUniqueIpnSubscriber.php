<?php

namespace App\EventSubscriber\UserSystem;

use App\Entity\Parts\Part;
use App\Settings\MiscSettings\IpnSuggestSettings;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\OnFlushEventArgs;

class PartUniqueIpnSubscriber implements EventSubscriber
{
    public function __construct(
        private IpnSuggestSettings $ipnSuggestSettings
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (!$this->ipnSuggestSettings->autoAppendSuffix) {
            return;
        }

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $meta = $em->getClassMetadata(Part::class);

        // Collect all IPNs already reserved in the current flush (so new entities do not collide with each other)
        $reservedIpns = [];

        // Helper to assign a collision-free IPN for a Part entity
        $ensureUnique = function (Part $part) use ($em, $uow, $meta, &$reservedIpns) {
            $ipn = $part->getIpn();
            if ($ipn === null || $ipn === '') {
                return;
            }

            // Check against IPNs already reserved in the current flush (except itself)
            $originalIpn = $ipn;
            $candidate = $originalIpn;
            $increment = 1;

            $conflicts = function (string $candidate) use ($em, $part, $reservedIpns) {
                // Collision within the current flush session?
                if (isset($reservedIpns[$candidate]) && $reservedIpns[$candidate] !== $part) {
                    return true;
                }
                // Collision with an existing DB row?
                $existing = $em->getRepository(Part::class)->findOneBy(['ipn' => $candidate]);
                return $existing !== null && $existing->getId() !== $part->getId();
            };

            while ($conflicts($candidate)) {
                $candidate = $originalIpn . '_' . $increment;
                $increment++;
            }

            if ($candidate !== $ipn) {
                $before = $part->getIpn();
                $part->setIpn($candidate);

                // Recompute the change set so Doctrine writes the change
                $uow->recomputeSingleEntityChangeSet($meta, $part);
                $reservedIpns[$candidate] = $part;

                // If the old IPN was reserved already, clean it up
                if ($before !== null && isset($reservedIpns[$before]) && $reservedIpns[$before] === $part) {
                    unset($reservedIpns[$before]);
                }
            } else {
                // Candidate unchanged, but reserve it so subsequent entities see it
                $reservedIpns[$candidate] = $part;
            }
        };

        // 1) Iterate over new entities
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Part) {
                $ensureUnique($entity);
            }
        }

        // 2) Iterate over updates (if IPN changed, ensure uniqueness again)
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Part) {
                $ensureUnique($entity);
            }
        }
    }
}
