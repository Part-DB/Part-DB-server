<?php

declare(strict_types=1);

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

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\LabelGenerationRequest;
use App\Entity\Base\AbstractDBElement;
use App\Entity\LabelSystem\LabelProfile;
use App\Repository\DBElementRepository;
use App\Repository\LabelProfileRepository;
use App\Services\ElementTypeNameGenerator;
use App\Services\LabelSystem\LabelGenerator;
use App\Services\Misc\RangeParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LabelGenerationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LabelGenerator $labelGenerator,
        private readonly RangeParser $rangeParser,
        private readonly ElementTypeNameGenerator $elementTypeNameGenerator,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        // Check if user has permission to create labels
        if (!$this->security->isGranted('@labels.create_labels')) {
            throw new AccessDeniedHttpException('You do not have permission to generate labels.');
        }

        /** @var LabelGenerationRequest $request */
        $request = $data;

        // Fetch the label profile
        /** @var LabelProfileRepository<LabelProfile> $profileRepo */
        $profileRepo = $this->entityManager->getRepository(LabelProfile::class);
        $profile = $profileRepo->find($request->profileId);
        if (!$profile instanceof LabelProfile) {
            throw new NotFoundHttpException(sprintf('Label profile with ID %d not found.', $request->profileId));
        }

        // Check if user has read permission for the profile
        if (!$this->security->isGranted('read', $profile)) {
            throw new AccessDeniedHttpException('You do not have permission to access this label profile.');
        }

        // Get label options from profile
        $options = $profile->getOptions();

        // Override element type if provided, otherwise use profile's default
        if ($request->elementType !== null) {
            $options->setSupportedElement($request->elementType);
        }

        // Parse element IDs from the range string
        try {
            $idArray = $this->rangeParser->parse($request->elementIds);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException('Invalid element IDs format: ' . $e->getMessage());
        }

        if (empty($idArray)) {
            throw new BadRequestHttpException('No valid element IDs provided.');
        }

        // Fetch the target entities
        /** @var DBElementRepository<AbstractDBElement> $repo */
        $repo = $this->entityManager->getRepository($options->getSupportedElement()->getEntityClass());

        $elements = $repo->getElementsFromIDArray($idArray);

        if (empty($elements)) {
            throw new NotFoundHttpException('No elements found with the provided IDs.');
        }

        // Generate the PDF
        try {
            $pdfContent = $this->labelGenerator->generateLabel($options, $elements);
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Failed to generate label: ' . $e->getMessage());
        }

        // Generate filename
        $filename = $this->generateFilename($elements[0], $profile);

        // Return PDF as response
        return new Response(
            $pdfContent,
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Content-Length' => (string) strlen($pdfContent),
            ]
        );
    }

    private function generateFilename(AbstractDBElement $element, LabelProfile $profile): string
    {
        $ret = 'label_' . $this->elementTypeNameGenerator->getLocalizedTypeLabel($element);
        $ret .= $element->getID();
        $ret .= '_' . preg_replace('/[^a-z0-9_\-]/i', '_', $profile->getName());

        return $ret . '.pdf';
    }
}
