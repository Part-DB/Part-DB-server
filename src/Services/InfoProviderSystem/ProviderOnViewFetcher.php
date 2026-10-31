<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);


namespace App\Services\InfoProviderSystem;

use App\Entity\Attachments\Attachment;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\InfoProviderReference;
use App\Entity\Parts\ManufacturingStatus;
use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Orderdetail;
use App\Services\LogSystem\EventCommentHelper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Fills a part with data from an info provider the first time its info page is opened.
 *
 * Looking up every part of a large inventory up front is expensive (Canopy bills per request) or impolite (many
 * requests to the website of a store), while most of these parts are never looked at again. For the providers this
 * is enabled for (see ProviderOnViewMatcher), a part is only looked up when somebody actually opens its page - and
 * only once: afterwards the part carries a provider reference, which marks it as done.
 *
 * Only missing data is filled in. Nothing the part already has is overwritten or extended, as nobody reviews
 * the result (in contrast to the "update from info provider" form).
 */
final class ProviderOnViewFetcher
{
    public const STATUS_UPDATED = 'updated';
    /** Nothing to do (anymore), e.g. because a parallel request already fetched the data */
    public const STATUS_UNCHANGED = 'unchanged';
    /** Another request is fetching the data for this part right now */
    public const STATUS_BUSY = 'busy';
    /** The daily request limit is used up */
    public const STATUS_LIMIT = 'limit';
    public const STATUS_ERROR = 'error';

    /**
     * @var int Seconds after which a fetch which never finished (e.g. a killed request) does not block new ones anymore.
     * A fetch can take a while, as some providers wait several seconds between their requests to a store.
     */
    private const LOCK_TTL = 300;
    /** @var int Seconds before a part whose fetch failed is tried again, so a broken product does not cost a request per page view */
    private const FAILURE_TTL = 3600 * 24;

    public function __construct(
        private readonly ProviderOnViewMatcher $matcher,
        private readonly PartInfoRetriever $infoRetriever,
        private readonly EntityManagerInterface $em,
        private readonly EventCommentHelper $commentHelper,
        private readonly CacheItemPoolInterface $partInfoCache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Returns how the data of the given part would be fetched, or null if there is nothing to fetch: the part has
     * info provider data already, no enabled provider recognizes it, or fetching it failed recently.
     * This check is cheap and does not contact a provider, so it can be done on every page view.
     */
    public function getPendingMatch(Part $part): ?OnViewMatch
    {
        if ($part->getID() === null) {
            return null;
        }

        foreach ($this->matcher->findMatches($part) as $match) {
            if (!$this->partInfoCache->hasItem($this->failureKey($part, $match))) {
                return $match;
            }
        }

        return null;
    }

    /**
     * Checks whether opening the page of this part should trigger a request to an info provider.
     */
    public function isFetchNeeded(Part $part): bool
    {
        return $this->getPendingMatch($part) !== null;
    }

    /**
     * Retrieves the data for the given part from the provider which recognizes it, and fills in what the part is
     * missing. Whatever goes wrong at the provider ends up as STATUS_ERROR, this does not throw.
     * @return string One of the STATUS_* constants
     */
    public function fetch(Part $part): string
    {
        $match = $this->getPendingMatch($part);
        if ($match === null) {
            return self::STATUS_UNCHANGED;
        }

        $lock_key = 'on_view_lock_'.$part->getID();
        $lock = $this->partInfoCache->getItem($lock_key);
        if ($lock->isHit()) {
            return self::STATUS_BUSY;
        }

        if (!$this->consumeDailyLimit($match->getProviderKey())) {
            return self::STATUS_LIMIT;
        }

        $lock->set(true);
        $lock->expiresAfter(self::LOCK_TTL);
        $this->partInfoCache->save($lock);

        try {
            $dto = $this->infoRetriever->getDetails($match->getProviderKey(), $this->resolveProviderId($match));
            $this->fillPart($part, $this->infoRetriever->dtoToPart($dto), $match);

            $this->commentHelper->setMessage(sprintf('Fetched data from %s on first page view', $match->getProviderName()));
            $this->em->flush();
        } catch (\Throwable $exception) {
            $this->logger->error('Could not fetch the data of part {id} from {provider} on page view: {message}', [
                'id' => $part->getID(),
                'provider' => $match->getProviderKey(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            //Remember the failure, so the same failing request is not repeated on every page view
            $failure = $this->partInfoCache->getItem($this->failureKey($part, $match));
            $failure->set(true);
            $failure->expiresAfter(self::FAILURE_TTL);
            $this->partInfoCache->save($failure);

            return self::STATUS_ERROR;
        } finally {
            $this->partInfoCache->deleteItem($lock_key);
        }

        return self::STATUS_UPDATED;
    }

    /**
     * Returns the ID of the product at the provider. If the part was only recognized by its supplier, the provider
     * is searched for the supplier part number, and the product must be found with exactly this number.
     */
    private function resolveProviderId(OnViewMatch $match): string
    {
        if ($match->providerId !== null) {
            return $match->providerId;
        }

        $supplier_part_nr = (string) $match->supplierPartNr;
        $results = $this->infoRetriever->searchByKeyword($supplier_part_nr, [$match->provider]);

        $result = $this->matcher->pickExactResult($match->provider, $supplier_part_nr, $results);
        if ($result === null) {
            throw new \RuntimeException(sprintf('%s has no (unambiguous) product with exactly the number "%s" (%d search results)',
                $match->getProviderName(), $supplier_part_nr, count($results)));
        }

        return $result->provider_id;
    }

    /**
     * Copies everything the target part is missing from the part built from the provider data.
     */
    private function fillPart(Part $target, Part $provider_part, OnViewMatch $match): void
    {
        if ($target->getDescription() === '') {
            $target->setDescription($provider_part->getDescription());
        }
        if ($target->getComment() === '') {
            $target->setComment($provider_part->getComment());
        }
        if ($target->getManufacturerProductNumber() === '') {
            $target->setManufacturerProductNumber($provider_part->getManufacturerProductNumber());
        }
        if ($target->getCustomProductURL() === '') {
            $target->setManufacturerProductURL($provider_part->getCustomProductURL());
        }
        if ($target->getGtin() === null) {
            $target->setGtin($provider_part->getGtin());
        }
        if ($target->getMass() === null) {
            $target->setMass($provider_part->getMass());
        }
        if (in_array($target->getManufacturingStatus(), [null, ManufacturingStatus::NOT_SET], true)
            && $provider_part->getManufacturingStatus() !== null) {
            $target->setManufacturingStatus($provider_part->getManufacturingStatus());
        }

        if ($target->getManufacturer() === null && $provider_part->getManufacturer() !== null) {
            //The manufacturer might be newly created for this part, so it has to be persisted explicitly
            $this->em->persist($provider_part->getManufacturer());
            $target->setManufacturer($provider_part->getManufacturer());
        }

        $this->fillAttachments($target, $provider_part);
        $this->fillParameters($target, $provider_part);
        $this->fillOrderdetails($target, $provider_part, $match);

        //Mark the part as done, and allow to update it from the provider via the normal info provider tools later
        $reference = $provider_part->getProviderReference();
        $target->setProviderReference(InfoProviderReference::providerReference(
            (string) $reference->getProviderKey(), (string) $reference->getProviderId(), $reference->getProviderUrl()
        ));
    }

    private function fillAttachments(Part $target, Part $provider_part): void
    {
        $existing_urls = [];
        foreach ($target->getAttachments() as $attachment) {
            $existing_urls[] = $attachment->getURL();
        }

        $master = $provider_part->getMasterPictureAttachment();

        /** @var Attachment $attachment */
        foreach ($provider_part->getAttachments()->toArray() as $attachment) {
            if (in_array($attachment->getURL(), $existing_urls, true)) {
                continue;
            }

            //The attachment type might be newly created for this part, so it has to be persisted explicitly
            if ($attachment->getAttachmentType() !== null) {
                $this->em->persist($attachment->getAttachmentType());
            }

            $provider_part->removeAttachment($attachment);
            $target->addAttachment($attachment);

            if ($attachment === $master && $target->getMasterPictureAttachment() === null) {
                $target->setMasterPictureAttachment($attachment);
            }
        }
    }

    private function fillParameters(Part $target, Part $provider_part): void
    {
        $existing_names = [];
        foreach ($target->getParameters() as $parameter) {
            $existing_names[] = mb_strtolower($parameter->getName());
        }

        /** @var AbstractParameter $parameter */
        foreach ($provider_part->getParameters()->toArray() as $parameter) {
            if (in_array(mb_strtolower($parameter->getName()), $existing_names, true)) {
                continue;
            }

            $provider_part->removeParameter($parameter);
            $target->addParameter($parameter);
        }
    }

    private function fillOrderdetails(Part $target, Part $provider_part, OnViewMatch $match): void
    {
        /** @var Orderdetail $orderdetail */
        foreach ($provider_part->getOrderdetails()->toArray() as $orderdetail) {
            //Find the orderdetail of the target part, which describes the same product at the same supplier
            $existing = null;
            foreach ($target->getOrderdetails() as $target_orderdetail) {
                if ($this->isSameOffer($target_orderdetail, $orderdetail, $match)) {
                    $existing = $target_orderdetail;
                    break;
                }
            }

            foreach ($orderdetail->getPricedetails() as $pricedetail) {
                //The currency might be newly created for this part, so it has to be persisted explicitly
                if ($pricedetail->getCurrency() !== null) {
                    $this->em->persist($pricedetail->getCurrency());
                }
            }

            if ($existing === null) {
                //The supplier might be newly created for this part, so it has to be persisted explicitly
                if ($orderdetail->getSupplier() !== null) {
                    $this->em->persist($orderdetail->getSupplier());
                }

                $provider_part->removeOrderdetail($orderdetail);
                $target->addOrderdetail($orderdetail);
                continue;
            }

            if ($existing->getSupplierPartNr() === '') {
                $existing->setSupplierpartnr($orderdetail->getSupplierPartNr());
            }
            if ($existing->getSupplierProductUrl() === '') {
                $existing->setSupplierProductUrl($orderdetail->getSupplierProductUrl());
            }

            //Only add the current price if the part has no price for this product yet (e.g. the price it was bought for)
            if ($existing->getPricedetails()->isEmpty()) {
                foreach ($orderdetail->getPricedetails()->toArray() as $pricedetail) {
                    $orderdetail->removePricedetail($pricedetail);
                    $existing->addPricedetail($pricedetail);
                }
                $existing->setPricesIncludesVAT($orderdetail->getPricesIncludesVAT());
            }
        }
    }

    /**
     * Checks if an orderdetail the part already has and one supplied by the provider are the same offer, so the
     * part does not end up with two orderdetails for it.
     */
    private function isSameOffer(Orderdetail $existing, Orderdetail $provided, OnViewMatch $match): bool
    {
        $existing_supplier = ProviderOnViewMatcher::normalizeName((string) $existing->getSupplier()?->getName());
        $provided_supplier = ProviderOnViewMatcher::normalizeName((string) $provided->getSupplier()?->getName());

        $provided_id = $this->matcher->getProviderIdFromURL($match->provider, $provided->getSupplierProductUrl());

        //The orderdetail the part was recognized by is the offer of this product at the store of the provider
        if ($existing === $match->orderdetail && ($provided_id !== null || $existing_supplier === $provided_supplier)) {
            return true;
        }

        //Both link to the same product page
        if ($provided_id !== null
            && $this->matcher->getProviderIdFromURL($match->provider, $existing->getSupplierProductUrl()) === $provided_id) {
            return true;
        }

        //The same order number at the same supplier
        return $existing_supplier !== '' && $existing_supplier === $provided_supplier
            && ProviderOnViewMatcher::numbersEqual($existing->getSupplierPartNr(), $provided->getSupplierPartNr(),
                [(string) $provided->getSupplier()?->getName()]);
    }

    /**
     * Counts a lookup against the daily limit of the provider and returns false if the limit is used up.
     */
    private function consumeDailyLimit(string $provider_key): bool
    {
        $limit = $this->matcher->getDailyLimit($provider_key);
        if ($limit <= 0) {
            return true;
        }

        $factory = new RateLimiterFactory([
            'id' => $provider_key.'_on_view',
            'policy' => 'sliding_window',
            'limit' => $limit,
            'interval' => '24 hours',
        ], new CacheStorage($this->partInfoCache));

        return $factory->create('daily')->consume()->isAccepted();
    }

    private function failureKey(Part $part, OnViewMatch $match): string
    {
        //Provider keys are identifiers, but make sure that nothing the cache does not allow in a key gets in
        return preg_replace('/[^A-Za-z0-9_.]/', '_', $match->getProviderKey()).'_on_view_failed_'.$part->getID();
    }
}
