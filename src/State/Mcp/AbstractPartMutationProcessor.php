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

namespace App\State\Mcp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\ValidatorInterface;
use App\Entity\Attachments\Attachment;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\AssociationType;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\InfoProviderReference;
use App\Entity\Parts\ManufacturingStatus;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartAssociation;
use App\Entity\Parts\PartCustomState;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\UserSystem\User;
use App\Mcp\DTO\AbstractPartWriteInput;
use App\Mcp\DTO\AssociatedPartInput;
use App\Mcp\DTO\EdaInfoInput;
use App\Mcp\DTO\OrderdetailInput;
use App\Mcp\DTO\ParameterInput;
use App\Mcp\DTO\PartLotInput;
use App\Mcp\DTO\PricedetailInput;
use App\Services\LogSystem\EventCommentHelper;
use App\Settings\AISettings\McpSettings;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Schema\Result\CallToolResult;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shared by CreatePartProcessor and UpdatePartProcessor. Owns the process() method as a template: the global
 * editing-enabled guard and expected-error-to-CallToolResult wrapping (McpToolErrorHandling) happen exactly once,
 * here, so neither subclass can forget them - each subclass only implements mutatePart() with its own
 * entity-resolution/permission-check/field-application logic. Also provides the shared helpers for resolving
 * related entities by ID (mirrors GetPartByIdProcessor's manual find()-or-404 idiom) and reconciling the nested
 * collection inputs (partLots/parameters/orderdetails/pricedetails/associatedPartsAsOwner) onto the corresponding
 * Doctrine collections via the entity's own adder/remover methods.
 */
abstract class AbstractPartMutationProcessor implements ProcessorInterface
{
    use McpToolErrorHandling;

    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly ValidatorInterface $validator,
        protected readonly EventCommentHelper $eventCommentHelper,
        protected readonly McpSettings $mcpSettings,
    ) {
    }

    final public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Part|CallToolResult
    {
        return $this->runCatchingExpectedErrors(function () use ($data) {
            $this->mcpSettings->assertEditingEnabled();

            return $this->mutatePart($data);
        });
    }

    abstract protected function mutatePart(mixed $data): Part;

    /**
     * Applies every field AbstractPartWriteInput declares (i.e. everything both create_part and update_part
     * share) onto $part, guarded uniformly by wasProvided() - used identically by CreatePartProcessor (against a
     * brand-new, still-empty Part, so every nested collection item is necessarily a fresh one) and
     * UpdatePartProcessor (against an existing Part, so nested collections are reconciled by id). "name" is
     * deliberately not handled here, since its required-ness differs between the two operations - each processor
     * sets it itself.
     */
    protected function applyProvidedFields(Part $part, AbstractPartWriteInput $data): void
    {
        //Non-nullable string/scalar fields on Part coerce an explicit `null` to their empty/default value, since
        //the entity's own setters don't accept null for them. Nullable fields (mass/ipn/gtin/relations) pass null
        //through as-is, which clears them (subject to the entity's own validation, e.g. category can never be null).
        if ($data->wasProvided('description')) {
            $part->setDescription($data->description ?? '');
        }
        if ($data->wasProvided('comment')) {
            $part->setComment($data->comment ?? '');
        }
        if ($data->wasProvided('favorite')) {
            $part->setFavorite($data->favorite ?? false);
        }
        if ($data->wasProvided('categoryId')) {
            $part->setCategory($data->categoryId !== null ? $this->resolveCategory($data->categoryId) : null);
        }
        if ($data->wasProvided('footprintId')) {
            $part->setFootprint($data->footprintId !== null ? $this->resolveFootprint($data->footprintId) : null);
        }
        if ($data->wasProvided('manufacturerId')) {
            $part->setManufacturer($data->manufacturerId !== null ? $this->resolveManufacturer($data->manufacturerId) : null);
        }
        if ($data->wasProvided('manufacturerProductUrl')) {
            $part->setManufacturerProductURL($data->manufacturerProductUrl ?? '');
        }
        if ($data->wasProvided('manufacturerProductNumber')) {
            $part->setManufacturerProductNumber($data->manufacturerProductNumber ?? '');
        }
        if ($data->wasProvided('manufacturingStatus')) {
            $this->applyManufacturingStatus($part, $data->manufacturingStatus);
        }
        if ($data->wasProvided('minAmount')) {
            $part->setMinAmount($data->minAmount ?? 0.0);
        }
        if ($data->wasProvided('partUnitId')) {
            $part->setPartUnit($data->partUnitId !== null ? $this->resolvePartUnit($data->partUnitId) : null);
        }
        if ($data->wasProvided('needsReview')) {
            $part->setNeedsReview($data->needsReview ?? false);
        }
        if ($data->wasProvided('tags')) {
            $part->setTags($data->tags ?? '');
        }
        if ($data->wasProvided('mass')) {
            $part->setMass($data->mass);
        }
        if ($data->wasProvided('ipn')) {
            $part->setIpn($data->ipn);
        }
        if ($data->wasProvided('partCustomStateId')) {
            $part->setPartCustomState($data->partCustomStateId !== null ? $this->resolvePartCustomState($data->partCustomStateId) : null);
        }
        if ($data->wasProvided('gtin')) {
            $part->setGtin($data->gtin);
        }
        if ($data->wasProvided('masterPictureAttachmentId')) {
            $part->setMasterPictureAttachment(
                $data->masterPictureAttachmentId !== null ? $this->resolveOwnAttachment($part, $data->masterPictureAttachmentId) : null
            );
        }

        if ($data->wasProvided('partLots')) {
            $this->reconcilePartLots($part, $data->partLots);
        }
        if ($data->wasProvided('parameters')) {
            $this->reconcileParameters($part, $data->parameters);
        }
        if ($data->wasProvided('orderdetails')) {
            $this->reconcileOrderdetails($part, $data->orderdetails);
        }
        if ($data->wasProvided('associatedPartsAsOwner')) {
            $this->reconcileAssociatedParts($part, $data->associatedPartsAsOwner);
        }
        if ($data->wasProvided('edaInfo') && $data->edaInfo !== null) {
            $this->applyEdaInfo($part, $data->edaInfo);
        }
        if ($data->wasProvided('providerKey') || $data->wasProvided('providerId') || $data->wasProvided('providerUrl')) {
            $this->applyProviderReference(
                $part,
                $data->providerKey,
                $data->wasProvided('providerKey'),
                $data->providerId,
                $data->wasProvided('providerId'),
                $data->providerUrl,
                $data->wasProvided('providerUrl'),
            );
        }
    }

    /**
     * Feeds a non-empty logComment into the audit log, shared by create_part and update_part.
     */
    protected function applyLogComment(?string $logComment): void
    {
        if ($logComment !== null && $logComment !== '' && !$this->eventCommentHelper->isMessageSet()) {
            $this->eventCommentHelper->setMessage($logComment);
        }
    }

    protected function resolveCategory(int $id): Category
    {
        return $this->findOrFail(Category::class, $id);
    }

    protected function resolveFootprint(int $id): Footprint
    {
        return $this->findOrFail(Footprint::class, $id);
    }

    protected function resolveManufacturer(int $id): Manufacturer
    {
        return $this->findOrFail(Manufacturer::class, $id);
    }

    protected function resolvePartUnit(int $id): MeasurementUnit
    {
        return $this->findOrFail(MeasurementUnit::class, $id);
    }

    protected function resolvePartCustomState(int $id): PartCustomState
    {
        return $this->findOrFail(PartCustomState::class, $id);
    }

    protected function resolveStorageLocation(int $id): StorageLocation
    {
        return $this->findOrFail(StorageLocation::class, $id);
    }

    protected function resolveSupplier(int $id): Supplier
    {
        return $this->findOrFail(Supplier::class, $id);
    }

    protected function resolveCurrency(int $id): Currency
    {
        return $this->findOrFail(Currency::class, $id);
    }

    protected function resolveUser(int $id): User
    {
        return $this->findOrFail(User::class, $id);
    }

    protected function resolveOtherPart(int $id): Part
    {
        return $this->findOrFail(Part::class, $id);
    }

    protected function resolveOwnAttachment(Part $part, int $id): Attachment
    {
        $attachment = $this->findOrFail(Attachment::class, $id);
        foreach ($part->getAttachments() as $existing) {
            if ($existing === $attachment) {
                return $attachment;
            }
        }

        throw new NotFoundHttpException(sprintf('Attachment with id %d does not belong to this part.', $id));
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    private function findOrFail(string $class, int $id): object
    {
        $entity = $this->entityManager->find($class, $id);
        if (!$entity instanceof $class) {
            throw new NotFoundHttpException(sprintf('%s with id %d not found.', (new \ReflectionClass($class))->getShortName(), $id));
        }

        return $entity;
    }

    /**
     * Only called when the field was actually provided (see applyProvidedFields()'s wasProvided() guard), so
     * $status === null here means "explicitly clear it", not "not provided" - it resets to NOT_SET rather than
     * being a no-op, consistent with every other clearable field.
     */
    protected function applyManufacturingStatus(Part $part, ?string $status): void
    {
        if ($status === null) {
            $part->setManufacturingStatus(ManufacturingStatus::NOT_SET);
            return;
        }

        try {
            $part->setManufacturingStatus(ManufacturingStatus::from($status));
        } catch (\ValueError) {
            throw new BadRequestHttpException(sprintf('"%s" is not a valid manufacturing status.', $status));
        }
    }

    /**
     * Rebuilds the part's InfoProviderReference from whichever of providerKey/providerId/providerUrl were
     * actually provided, keeping the current value for any that weren't. InfoProviderReference has no setters of
     * its own (only static factories), so it must always be replaced wholesale, not mutated field-by-field.
     *
     * A resulting key+id pair links the part to that provider (and stamps a fresh "last updated" timestamp, via
     * InfoProviderReference::providerReference()); both null unlinks it entirely; a mismatched pair (one set,
     * the other not) is deliberately passed through as-is so Part-level validation reports it clearly, rather
     * than silently guessing what the caller meant.
     */
    protected function applyProviderReference(
        Part $part,
        ?string $providerKey,
        bool $providerKeyProvided,
        ?string $providerId,
        bool $providerIdProvided,
        ?string $providerUrl,
        bool $providerUrlProvided,
    ): void {
        $current = $part->getProviderReference();
        $newKey = $providerKeyProvided ? $providerKey : $current->getProviderKey();
        $newId = $providerIdProvided ? $providerId : $current->getProviderId();
        $newUrl = $providerUrlProvided ? $providerUrl : $current->getProviderUrl();

        if ($newKey === null && $newId === null) {
            $part->setProviderReference(InfoProviderReference::noProvider());

            return;
        }

        if ($newKey !== null && $newId !== null) {
            $part->setProviderReference(InfoProviderReference::providerReference($newKey, $newId, $newUrl));

            return;
        }

        //Mismatched key/id (only one set) - keep the existing timestamp and let Part-level validation reject it
        $part->setProviderReference(InfoProviderReference::create($newKey, $newId, $newUrl, $current->getLastUpdated()));
    }

    protected function applyEdaInfo(Part $part, EdaInfoInput $input): void
    {
        $eda = $part->getEdaInfo();
        $eda->setReferencePrefix($input->referencePrefix);
        $eda->setValue($input->value);
        $eda->setVisibility($input->visibility);
        $eda->setExcludeFromBom($input->excludeFromBom);
        $eda->setExcludeFromBoard($input->excludeFromBoard);
        $eda->setExcludeFromSim($input->excludeFromSim);
        $eda->setKicadSymbol($input->kicadSymbol);
        $eda->setKicadFootprint($input->kicadFootprint);
    }

    protected function applyPartLotFields(PartLot $lot, PartLotInput $input, bool $allowAmount): void
    {
        if ($input->amount !== null) {
            if (!$allowAmount) {
                throw new BadRequestHttpException('The amount of an existing part lot cannot be changed here; use the withdraw_part_stock/add_part_stock/stocktake_part_lot tools instead.');
            }
            $lot->setAmount($input->amount);
        }
        if ($input->description !== null) {
            $lot->setDescription($input->description);
        }
        if ($input->comment !== null) {
            $lot->setComment($input->comment);
        }
        if ($input->expirationDate !== null) {
            $lot->setExpirationDate($input->expirationDate !== '' ? new \DateTimeImmutable($input->expirationDate) : null);
        }
        if ($input->storageLocationId !== null) {
            $lot->setStorageLocation($this->resolveStorageLocation($input->storageLocationId));
        }
        if ($input->instockUnknown !== null) {
            $lot->setInstockUnknown($input->instockUnknown);
        }
        if ($input->needsRefill !== null) {
            $lot->setNeedsRefill($input->needsRefill);
        }
        if ($input->ownerId !== null) {
            $lot->setOwner($this->resolveUser($input->ownerId));
        }
        if ($input->userBarcode !== null) {
            $lot->setUserBarcode($input->userBarcode !== '' ? $input->userBarcode : null);
        }
    }

    protected function applyParameterFields(PartParameter $parameter, ParameterInput $input): void
    {
        $parameter->setName($input->name);
        if ($input->group !== null) {
            $parameter->setGroup($input->group);
        }
        if ($input->symbol !== null) {
            $parameter->setSymbol($input->symbol);
        }
        if ($input->valueMin !== null) {
            $parameter->setValueMin($input->valueMin);
        }
        if ($input->valueTypical !== null) {
            $parameter->setValueTypical($input->valueTypical);
        }
        if ($input->valueMax !== null) {
            $parameter->setValueMax($input->valueMax);
        }
        if ($input->unit !== null) {
            $parameter->setUnit($input->unit);
        }
        if ($input->valueText !== null) {
            $parameter->setValueText($input->valueText);
        }
    }

    protected function applyOrderdetailFields(Orderdetail $orderdetail, OrderdetailInput $input): void
    {
        $orderdetail->setSupplier($this->resolveSupplier($input->supplierId));
        if ($input->supplierPartNr !== null) {
            $orderdetail->setSupplierpartnr($input->supplierPartNr);
        }
        if ($input->obsolete !== null) {
            $orderdetail->setObsolete($input->obsolete);
        }
        if ($input->supplierProductUrl !== null) {
            $orderdetail->setSupplierProductUrl($input->supplierProductUrl);
        }
        if ($input->pricesIncludesVat !== null) {
            $orderdetail->setPricesIncludesVAT($input->pricesIncludesVat);
        }

        $this->reconcilePricedetails($orderdetail, $input->pricedetails);
    }

    /**
     * @param array<int, array<string, mixed>> $rawItems
     */
    protected function reconcilePricedetails(Orderdetail $orderdetail, array $rawItems): void
    {
        $existingById = [];
        foreach ($orderdetail->getPricedetails() as $pricedetail) {
            if ($pricedetail->getID() !== null) {
                $existingById[$pricedetail->getID()] = $pricedetail;
            }
        }

        $seenIds = [];
        foreach ($rawItems as $raw) {
            $input = PricedetailInput::fromArray($raw);
            if ($input->id !== null) {
                $pricedetail = $existingById[$input->id]
                    ?? throw new NotFoundHttpException(sprintf('Pricedetail with id %d does not belong to this orderdetail.', $input->id));
                $seenIds[] = $input->id;
            } else {
                $pricedetail = new Pricedetail();
                $orderdetail->addPricedetail($pricedetail);
            }

            $pricedetail->setPrice(BigDecimal::of($input->price));
            if ($input->currencyId !== null) {
                $pricedetail->setCurrency($this->resolveCurrency($input->currencyId));
            }
            if ($input->priceRelatedQuantity !== null) {
                $pricedetail->setPriceRelatedQuantity($input->priceRelatedQuantity);
            }
            if ($input->minDiscountQuantity !== null) {
                $pricedetail->setMinDiscountQuantity($input->minDiscountQuantity);
            }
        }

        foreach ($existingById as $id => $pricedetail) {
            if (!in_array($id, $seenIds, true)) {
                $orderdetail->removePricedetail($pricedetail);
            }
        }
    }

    protected function applyAssociationFields(PartAssociation $association, AssociatedPartInput $input): void
    {
        $association->setOther($this->resolveOtherPart($input->otherPartId));
        $association->setType($this->resolveAssociationType($input->type));
        if ($input->otherType !== null) {
            $association->setOtherType($input->otherType !== '' ? $input->otherType : null);
        }
        if ($input->comment !== null) {
            $association->setComment($input->comment !== '' ? $input->comment : null);
        }
    }

    private function resolveAssociationType(string $name): AssociationType
    {
        foreach (AssociationType::cases() as $case) {
            if ($case->name === strtoupper($name)) {
                return $case;
            }
        }

        throw new BadRequestHttpException(sprintf('"%s" is not a valid association type.', $name));
    }

    /**
     * Reconciles $rawItems onto $part's part lots by id: an item with an `id` updates the matching existing lot
     * (404 if it doesn't belong to this part), an item without one creates a new lot, and any existing lot not
     * referenced by any item is removed. For a brand-new part (called from CreatePartProcessor) the lot
     * collection starts empty, so this naturally reduces to "create everything, any given id 404s".
     *
     * @param array<int, array<string, mixed>> $rawItems
     */
    protected function reconcilePartLots(Part $part, array $rawItems): void
    {
        $existingById = [];
        foreach ($part->getPartLots() as $lot) {
            if ($lot->getID() !== null) {
                $existingById[$lot->getID()] = $lot;
            }
        }

        $seenIds = [];
        foreach ($rawItems as $raw) {
            $input = PartLotInput::fromArray($raw);
            if ($input->id !== null) {
                $lot = $existingById[$input->id]
                    ?? throw new NotFoundHttpException(sprintf('Part lot with id %d does not belong to this part.', $input->id));
                $this->applyPartLotFields($lot, $input, allowAmount: false);
                $seenIds[] = $input->id;
            } else {
                $lot = new PartLot();
                $this->applyPartLotFields($lot, $input, allowAmount: true);
                $part->addPartLot($lot);
            }
        }

        foreach ($existingById as $id => $lot) {
            if (!in_array($id, $seenIds, true)) {
                $part->removePartLot($lot);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rawItems
     * @see self::reconcilePartLots() for the general by-id reconciliation rule
     */
    protected function reconcileParameters(Part $part, array $rawItems): void
    {
        $existingById = [];
        foreach ($part->getParameters() as $parameter) {
            if ($parameter->getID() !== null) {
                $existingById[$parameter->getID()] = $parameter;
            }
        }

        $seenIds = [];
        foreach ($rawItems as $raw) {
            $input = ParameterInput::fromArray($raw);
            if ($input->id !== null) {
                $parameter = $existingById[$input->id]
                    ?? throw new NotFoundHttpException(sprintf('Parameter with id %d does not belong to this part.', $input->id));
                $this->applyParameterFields($parameter, $input);
                $seenIds[] = $input->id;
            } else {
                $parameter = new PartParameter();
                $this->applyParameterFields($parameter, $input);
                $part->addParameter($parameter);
            }
        }

        foreach ($existingById as $id => $parameter) {
            if (!in_array($id, $seenIds, true)) {
                $part->removeParameter($parameter);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rawItems
     * @see self::reconcilePartLots() for the general by-id reconciliation rule
     */
    protected function reconcileOrderdetails(Part $part, array $rawItems): void
    {
        $existingById = [];
        foreach ($part->getOrderdetails() as $orderdetail) {
            if ($orderdetail->getID() !== null) {
                $existingById[$orderdetail->getID()] = $orderdetail;
            }
        }

        $seenIds = [];
        foreach ($rawItems as $raw) {
            $input = OrderdetailInput::fromArray($raw);
            if ($input->id !== null) {
                $orderdetail = $existingById[$input->id]
                    ?? throw new NotFoundHttpException(sprintf('Orderdetail with id %d does not belong to this part.', $input->id));
                $this->applyOrderdetailFields($orderdetail, $input);
                $seenIds[] = $input->id;
            } else {
                $orderdetail = new Orderdetail();
                $this->applyOrderdetailFields($orderdetail, $input);
                $part->addOrderdetail($orderdetail);
            }
        }

        foreach ($existingById as $id => $orderdetail) {
            if (!in_array($id, $seenIds, true)) {
                $part->removeOrderdetail($orderdetail);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rawItems
     * @see self::reconcilePartLots() for the general by-id reconciliation rule
     */
    protected function reconcileAssociatedParts(Part $part, array $rawItems): void
    {
        $existingById = [];
        foreach ($part->getAssociatedPartsAsOwner() as $association) {
            if ($association->getID() !== null) {
                $existingById[$association->getID()] = $association;
            }
        }

        $seenIds = [];
        foreach ($rawItems as $raw) {
            $input = AssociatedPartInput::fromArray($raw);
            if ($input->id !== null) {
                $association = $existingById[$input->id]
                    ?? throw new NotFoundHttpException(sprintf('Associated part with id %d does not belong to this part.', $input->id));
                $this->applyAssociationFields($association, $input);
                $seenIds[] = $input->id;
            } else {
                $association = new PartAssociation();
                $this->applyAssociationFields($association, $input);
                $part->addAssociatedPartsAsOwner($association);
            }
        }

        foreach ($existingById as $id => $association) {
            if (!in_array($id, $seenIds, true)) {
                $part->removeAssociatedPartsAsOwner($association);
            }
        }
    }
}
