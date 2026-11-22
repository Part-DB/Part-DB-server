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

use ApiPlatform\Validator\ValidatorInterface;
use App\ApiResource\InfoProviderSystem\InfoProviderPartWriteResult;
use App\ApiResource\InfoProviderSystem\PartFieldChange;
use App\Entity\Attachments\AttachmentUpload;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Exceptions\AttachmentDownloadException;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\EntityMergers\Mergers\PartMerger;
use App\Services\InfoProviderSystem\Providers\AIDocumentProvider;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use App\Settings\SystemSettings\AttachmentsSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Creates or updates a part from an info provider, for callers which have no human to review the result
 * (the REST API and the MCP tools).
 *
 * This deliberately reuses the very same chain the web UI uses - PartInfoRetriever to fetch and convert the
 * provider data, and PartMerger to combine it with what is already stored - so an automated update produces
 * the same outcome as a manual one. The difference is the missing review step: the web UI hands the merged
 * part to a form and lets a user save it, while here the result is persisted right away (unless a dry run was
 * requested), which is why every write goes through entity validation first.
 */
final readonly class PartFromProviderWriter
{
    public function __construct(
        private PartInfoRetriever $infoRetriever,
        private PartMerger $partMerger,
        private PartChangeSetBuilder $changeSetBuilder,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private AttachmentSubmitHandler $attachmentSubmitHandler,
        private AttachmentsSettings $attachmentsSettings,
        private UploadedDocumentAttachmentHelper $uploadedDocumentHelper,
    ) {
    }

    /**
     * Updates the given part from an info provider.
     *
     * @param  string|null  $provider_key The provider to update from, or null to use the one the part was created with
     * @param  string|null  $provider_id  The provider-specific ID, or null to use the one the part was created with
     */
    public function update(Part $part, ?string $provider_key, ?string $provider_id, bool $no_cache, bool $dry_run): InfoProviderPartWriteResult
    {
        [$provider_key, $provider_id] = $this->resolveProviderReference($part, $provider_key, $provider_id);

        $dto = $this->infoRetriever->getDetails($provider_key, $provider_id,
            [InfoProviderInterface::OPTION_NO_CACHE => $no_cache]);

        $this->partMerger->merge($part, $this->infoRetriever->dtoToPart($dto));

        $changes = $this->changeSetBuilder->build($part);

        if ($changes === [] && !$dry_run) {
            //Nothing to write, so there is also nothing to validate
            return InfoProviderPartWriteResult::of('unchanged', [], $part);
        }

        $status = $changes === [] ? 'unchanged' : 'updated';

        return $this->finish($part, $status, $changes, $dry_run);
    }

    /**
     * Creates a new part from an info provider.
     *
     * @param  int|null  $category_id The category to file the part under, see InfoProviderCreatePartInput
     */
    public function create(string $provider_key, string $provider_id, ?int $category_id, bool $no_cache, bool $dry_run): InfoProviderPartWriteResult
    {
        $part = $this->infoRetriever->createPart($provider_key, $provider_id,
            [InfoProviderInterface::OPTION_NO_CACHE => $no_cache]);

        if ($category_id !== null) {
            $part->setCategory(
                $this->entityManager->find(Category::class, $category_id)
                ?? throw new NotFoundHttpException(sprintf('Category with id %d not found.', $category_id))
            );
        }

        //The ai_document provider uses the provider_id as the token of the uploaded source file. The browser
        //attaches it via UploadedDocumentAttachmentHelper; without a form we do it here, so the source file
        //is not lost on a successful create. The file is only written to disk during the real write (not dry run):
        //the helper only marks the attachment with the token; the actual copy happens in applyAttachmentDefaults().
        if ($provider_key === AIDocumentProvider::PROVIDER_KEY) {
            $this->uploadedDocumentHelper->attachToPart($part, $provider_id);
        }

        //Persisting (without flushing) lets Doctrine compute the change set, so a new part reports its fields
        //the same way an updated one does - which is what makes a dry run useful here
        $this->entityManager->persist($part);
        $changes = $this->changeSetBuilder->build($part);

        return $this->finish($part, 'created', $changes, $dry_run);
    }

    /**
     * Validates the pending changes and either writes them or, for a dry run, throws them away.
     *
     * Validation runs for a dry run as well, so that a preview cannot report success for a write which would
     * be rejected afterwards (a part created from provider data alone, for example, usually still needs a
     * category before Part-DB accepts it).
     *
     * @param PartFieldChange[] $changes
     */
    private function finish(Part $part, string $status, array $changes, bool $dry_run): InfoProviderPartWriteResult
    {
        $this->persistNewRelatedEntities($part);

        try {
            $this->validateNewRelatedEntities($part);
            $this->validator->validate($part);
        } finally {
            if ($dry_run) {
                //Nothing was flushed, so discarding the entity manager leaves the database untouched
                $this->entityManager->clear();
            }
        }

        if ($dry_run) {
            return InfoProviderPartWriteResult::dryRun($status, $changes);
        }

        $this->applyAttachmentDefaults($part);
        $this->entityManager->flush();

        return InfoProviderPartWriteResult::of($status, $changes, $part);
    }

    /**
     * Applies the same attachment save defaults the form layer applies to new provider attachments:
     *   1. Downloads external URLs to local storage if the system is configured to do so (downloadByDefault).
     *   2. Sets a first picture as the part's preview image if none is set yet (becomePreviewIfEmpty).
     *   3. Saves any uploaded source file referenced by its token (the ai_document case).
     *
     * Only called for real writes, not dry runs, since downloading and saving files is irreversible.
     * Download failures are silently skipped so that a network hiccup does not abort the whole write.
     */
    private function applyAttachmentDefaults(Part $part): void
    {
        $shouldDownload = $this->attachmentsSettings->downloadByDefault && $this->attachmentsSettings->allowDownloads;

        foreach ($part->getAttachments() as $attachment) {
            if ($attachment->getID() !== null) {
                continue; // Only process new attachments (no DB id yet)
            }

            $existing = $attachment->getUpload();

            $upload = new AttachmentUpload(
                file: $existing?->file,
                data: $existing?->data,
                filename: $existing?->filename,
                downloadUrl: $shouldDownload && $attachment->hasExternal(),
                private: $existing?->private,
                becomePreviewIfEmpty: true,
                uploadedDocumentToken: $existing?->uploadedDocumentToken,
            );

            try {
                $this->attachmentSubmitHandler->handleUpload($attachment, $upload);
            } catch (AttachmentDownloadException|\RuntimeException) {
                // Keep the attachment as an external link if download fails rather than aborting the write
            }
        }
    }

    /**
     * Validates structural entities that the provider created on the fly (and that have no DB id yet), so
     * that a dry_run gives the same validation outcome as the real write - a name that is too long will fail
     * validation here rather than silently passing the preview and then failing during flush.
     */
    private function validateNewRelatedEntities(Part $part): void
    {
        $candidates = [$part->getCategory(), $part->getFootprint(), $part->getManufacturer(), $part->getPartUnit()];

        foreach ($part->getAttachments() as $attachment) {
            $candidates[] = $attachment->getAttachmentType();
        }

        foreach ($part->getPartLots() as $lot) {
            $candidates[] = $lot->getStorageLocation();
        }

        foreach ($part->getOrderdetails() as $orderdetail) {
            $candidates[] = $orderdetail->getSupplier();
        }

        foreach ($candidates as $entity) {
            while ($entity !== null && $entity->getID() === null) {
                $this->validator->validate($entity);
                $entity = $entity instanceof AbstractStructuralDBElement ? $entity->getParent() : null;
            }
        }
    }

    /**
     * Persists the manufacturers, footprints, suppliers, attachment types and currencies which the provider data
     * introduced but which do not exist in this Part-DB yet.
     *
     * DTOtoEntityConverter creates those on the fly (see its findOrCreateForInfoProvider() calls) without
     * persisting them - in the web UI that is done by the form layer, when the part form's structural entity
     * fields are processed (see StructuralEntityChoiceLoader). Without a form in the way, nobody would, and
     * flushing the part would fail on the unpersisted associations.
     */
    private function persistNewRelatedEntities(Part $part): void
    {
        $candidates = [$part->getCategory(), $part->getFootprint(), $part->getManufacturer(), $part->getPartUnit()];

        foreach ($part->getAttachments() as $attachment) {
            $candidates[] = $attachment->getAttachmentType();
        }

        foreach ($part->getPartLots() as $lot) {
            $candidates[] = $lot->getStorageLocation();
        }

        foreach ($part->getOrderdetails() as $orderdetail) {
            $candidates[] = $orderdetail->getSupplier();

            foreach ($orderdetail->getPricedetails() as $pricedetail) {
                $candidates[] = $pricedetail->getCurrency();
            }
        }

        foreach ($candidates as $entity) {
            //A structural element can be created together with a whole parent path, so walk up until we reach
            //one that is already known to the database
            while ($entity !== null && $entity->getID() === null) {
                $this->entityManager->persist($entity);

                $entity = $entity instanceof AbstractStructuralDBElement ? $entity->getParent() : null;
            }
        }
    }

    /**
     * @return array{string, string} The provider key and ID to update from
     */
    private function resolveProviderReference(Part $part, ?string $provider_key, ?string $provider_id): array
    {
        if ($provider_key !== null && $provider_id !== null) {
            return [$provider_key, $provider_id];
        }

        if ($provider_key !== null || $provider_id !== null) {
            throw new ConflictHttpException('provider_key and provider_id must be given together.');
        }

        $reference = $part->getProviderReference();

        if (!$reference->isProviderCreated()) {
            throw new ConflictHttpException(
                'This part was not created from an info provider, so there is nothing to update it from. '
                .'Pass provider_key and provider_id explicitly to link it to a provider part.'
            );
        }

        return [$reference->getProviderKey(), $reference->getProviderId()];
    }
}
