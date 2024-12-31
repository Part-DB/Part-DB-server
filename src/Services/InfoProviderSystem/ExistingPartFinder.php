<?php

namespace App\Services\InfoProviderSystem;

use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This service assists in finding existing local parts for a SearchResultDTO, so that the user
 * does not accidentally add a duplicate.
 */
final class ExistingPartFinder
{
    public function __construct(private readonly EntityManagerInterface $em)
    {

    }

    /**
     * Return the first existing local part, that matches the search result.
     * If no part is found, return null.
     * @param SearchResultDTO $dto
     * @return Part|null
     */
    public function findFirstExisting(SearchResultDTO $dto): ?Part
    {
        $results = $this->findAllExisting($dto);
        return count($results) > 0 ? $results[0] : null;
    }

    /**
     * Returns all existing local parts that match the search result.
     * If no part is found, return an empty array.
     * @param SearchResultDTO $dto
     * @return Part[]
     */
    public function findAllExisting(SearchResultDTO $dto): array
    {
        $qb = $this->em->getRepository(Part::class)->createQueryBuilder('part');
        $qb->select('part')
            ->leftJoin('part.manufacturer', 'manufacturer')
            ->Orwhere($qb->expr()->andX(
                'part.providerReference.provider_key = :providerKey',
                'part.providerReference.provider_id = :providerId',
            ))

            //Or the manufacturer and the MPN must match
            ->OrWhere(
                $qb->expr()->andX(
                    "ILIKE(manufacturer.name, :manufacturerName) = TRUE",
                    "ILIKE(part.manufacturer_product_number, :mpn) = TRUE"
                )
            )
        ;

        $qb->setParameter('providerKey', $dto->provider_key);
        $qb->setParameter('providerId', $dto->provider_id);

        $qb->setParameter('manufacturerName', $dto->manufacturer);
        $qb->setParameter('mpn', $dto->mpn);

        return $qb->getQuery()->getResult();
    }
}