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
use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Orderdetail;
use App\Services\InfoProviderSystem\Providers\CanopyProvider;
use App\Services\LogSystem\EventCommentHelper;
use App\Settings\InfoProviderSystem\CanopySettings;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Fills an Amazon part with data from Canopy the first time its info page is opened.
 *
 * Canopy bills per request, so retrieving data for every Amazon part of a large inventory up front is expensive,
 * while most of these parts are never looked at again. With the "fetch on view" setting of the Canopy provider
 * enabled, a part is only looked up when somebody actually opens its page - and only once: afterwards the part
 * carries a Canopy provider reference, which marks it as done.
 *
 * Only missing data is filled in. Nothing the part already has is overwritten or extended, as nobody reviews
 * the result (in contrast to the "update from info provider" form).
 */
final class CanopyOnViewFetcher
{
    public const STATUS_UPDATED = 'updated';
    /** Nothing to do (anymore), e.g. because a parallel request already fetched the data */
    public const STATUS_UNCHANGED = 'unchanged';
    /** Another request is fetching the data for this part right now */
    public const STATUS_BUSY = 'busy';
    /** The daily request limit is used up */
    public const STATUS_LIMIT = 'limit';
    public const STATUS_ERROR = 'error';

    /** @var int Seconds after which a fetch which never finished (e.g. a killed request) does not block new ones anymore */
    private const LOCK_TTL = 120;
    /** @var int Seconds before a part whose fetch failed is tried again, so a broken ASIN does not cost a request per page view */
    private const FAILURE_TTL = 3600 * 24;

    public function __construct(
        private readonly CanopySettings $settings,
        private readonly CanopyProvider $provider,
        private readonly PartInfoRetriever $infoRetriever,
        private readonly EntityManagerInterface $em,
        private readonly EventCommentHelper $commentHelper,
        private readonly CacheItemPoolInterface $partInfoCache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Returns the ASIN of the Amazon product behind this part, or null if it is not (recognizably) an Amazon part.
     * A part is an Amazon part, if it was created via the Canopy provider or if one of its orderdetails links to
     * a product page of the Amazon marketplace configured for the Canopy provider (this includes URLs generated
     * from the supplier's product URL template and the supplier part number).
     */
    public function getASIN(Part $part): ?string
    {
        $reference = $part->getProviderReference();
        if ($reference->getProviderKey() === CanopyProvider::PROVIDER_KEY && $this->isASIN($reference->getProviderId())) {
            return $reference->getProviderId();
        }

        foreach ($part->getOrderdetails() as $orderdetail) {
            $asin = $this->getASINFromURL($orderdetail->getSupplierProductUrl());
            if ($asin !== null) {
                return $asin;
            }
        }

        return null;
    }

    /**
     * Checks whether opening the page of this part should trigger a Canopy request.
     * This check is cheap and does not contact Canopy, so it can be done on every page view.
     */
    public function isFetchNeeded(Part $part): bool
    {
        if (!$this->settings->fetchOnView || !$this->provider->isActive() || $part->getID() === null) {
            return false;
        }

        //A provider reference means the part data already came from an info provider (Canopy or another one)
        if ($part->getProviderReference()->isProviderCreated()) {
            return false;
        }

        if ($this->getASIN($part) === null) {
            return false;
        }

        return !$this->partInfoCache->hasItem($this->failureKey($part));
    }

    /**
     * Retrieves the data for the given part from Canopy and fills in what the part is missing.
     * @return string One of the STATUS_* constants
     */
    public function fetch(Part $part): string
    {
        if (!$this->isFetchNeeded($part)) {
            return self::STATUS_UNCHANGED;
        }

        $lock = $this->partInfoCache->getItem('canopy_on_view_lock_'.$part->getID());
        if ($lock->isHit()) {
            return self::STATUS_BUSY;
        }

        if (!$this->consumeDailyLimit()) {
            return self::STATUS_LIMIT;
        }

        $lock->set(true);
        $lock->expiresAfter(self::LOCK_TTL);
        $this->partInfoCache->save($lock);

        try {
            $dto = $this->infoRetriever->getDetails(CanopyProvider::PROVIDER_KEY, (string) $this->getASIN($part));
            $this->fillPart($part, $this->infoRetriever->dtoToPart($dto));

            $this->commentHelper->setMessage('Fetched data from Canopy on first page view');
            $this->em->flush();
        } catch (\Throwable $exception) {
            $this->logger->error('Could not fetch the data of part {id} from Canopy on page view: {message}', [
                'id' => $part->getID(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            //Remember the failure, so we do not pay for the same failing request on every page view
            $failure = $this->partInfoCache->getItem($this->failureKey($part));
            $failure->set(true);
            $failure->expiresAfter(self::FAILURE_TTL);
            $this->partInfoCache->save($failure);

            return self::STATUS_ERROR;
        } finally {
            $this->partInfoCache->deleteItem('canopy_on_view_lock_'.$part->getID());
        }

        return self::STATUS_UPDATED;
    }

    /**
     * Copies everything the target part is missing from the part built from the provider data.
     */
    private function fillPart(Part $target, Part $provider_part): void
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

        if ($target->getManufacturer() === null && $provider_part->getManufacturer() !== null) {
            //The manufacturer might be newly created for this part, so it has to be persisted explicitly
            $this->em->persist($provider_part->getManufacturer());
            $target->setManufacturer($provider_part->getManufacturer());
        }

        $this->fillAttachments($target, $provider_part);
        $this->fillParameters($target, $provider_part);
        $this->fillOrderdetails($target, $provider_part);

        //Mark the part as done, and allow to update it from Canopy via the normal info provider tools later
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

    private function fillOrderdetails(Part $target, Part $provider_part): void
    {
        /** @var Orderdetail $orderdetail */
        foreach ($provider_part->getOrderdetails()->toArray() as $orderdetail) {
            $asin = $this->getASINFromURL($orderdetail->getSupplierProductUrl()) ?? $orderdetail->getSupplierPartNr();

            //Find the orderdetail of the target part, which describes the same Amazon product
            $existing = null;
            foreach ($target->getOrderdetails() as $target_orderdetail) {
                if ($target_orderdetail->getSupplierPartNr() === $asin
                    || $this->getASINFromURL($target_orderdetail->getSupplierProductUrl()) === $asin) {
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
     * Counts a request against the daily limit and returns false if the limit is used up.
     */
    private function consumeDailyLimit(): bool
    {
        $limit = $this->settings->fetchOnViewDailyLimit;
        if ($limit <= 0) {
            return true;
        }

        $factory = new RateLimiterFactory([
            'id' => 'canopy_on_view',
            'policy' => 'sliding_window',
            'limit' => $limit,
            'interval' => '24 hours',
        ], new CacheStorage($this->partInfoCache));

        return $factory->create('daily')->consume()->isAccepted();
    }

    private function failureKey(Part $part): string
    {
        return 'canopy_on_view_failed_'.$part->getID();
    }

    private function isASIN(?string $value): bool
    {
        return $value !== null && preg_match('/^[A-Z0-9]{10}$/', $value) === 1;
    }

    /**
     * Extracts the ASIN from an Amazon product page URL (like https://www.amazon.com/dp/B00EXAMPLE),
     * or returns null if the URL is not a product page of the configured Amazon marketplace.
     */
    private function getASINFromURL(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        //Canopy is queried for the configured Amazon marketplace only, an ASIN of another one would give wrong data
        $domain = $this->settings->getRealDomain();
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || (strcasecmp($host, $domain) !== 0 && !str_ends_with(strtolower($host), '.'.$domain))) {
            return null;
        }

        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('#/(?:dp|gp/product|gp/aw/d|exec/obidos/ASIN)/([A-Z0-9]{10})(?:[/?]|$)#', $path, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
