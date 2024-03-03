<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use App\Entity\Attachments\Attachment;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parameters\AttachmentTypeParameter;
use App\Entity\Parameters\CategoryParameter;
use App\Entity\Parameters\ProjectParameter;
use App\Entity\Parameters\FootprintParameter;
use App\Entity\Parameters\GroupParameter;
use App\Entity\Parameters\ManufacturerParameter;
use App\Entity\Parameters\MeasurementUnitParameter;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parameters\StorageLocationParameter;
use App\Entity\Parameters\SupplierParameter;
use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Currency;
use App\Repository\ParameterRepository;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\Attachments\BuiltinAttachmentsFinder;
use App\Services\Attachments\PartPreviewGenerator;
use App\Services\Tools\TagFinder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * In this controller the endpoints for the typeaheads are collected.
 */
#[Route(path: '/typeahead')]
class TypeaheadController extends AbstractController
{
    public function __construct(protected AttachmentURLGenerator $urlGenerator, protected Packages $assets)
    {
    }

    #[Route(path: '/builtInResources/search', name: 'typeahead_builtInRessources')]
    public function builtInResources(Request $request, BuiltinAttachmentsFinder $finder): JsonResponse
    {
        $query = $request->get('query');
        $array = $finder->find($query);

        $result = [];

        foreach ($array as $path) {
            $result[] = [
                'name' => $path,
                'image' => $this->assets->getUrl($this->urlGenerator->placeholderPathToAssetPath($path)),
            ];
        }

        $normalizers = [
            new ObjectNormalizer(),
        ];
        $encoders = [
            new JsonEncoder(),
        ];
        $serializer = new Serializer($normalizers, $encoders);
        $data = $serializer->serialize($result, 'json');

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }

    /**
     * This function map the parameter type to the class, so we can access its repository
     * @return class-string
     */
    private function typeToParameterClass(string $type): string
    {
        return match ($type) {
            'category' => CategoryParameter::class,
            'part' => PartParameter::class,
            'device' => ProjectParameter::class,
            'footprint' => FootprintParameter::class,
            'manufacturer' => ManufacturerParameter::class,
            'storelocation' => StorageLocationParameter::class,
            'supplier' => SupplierParameter::class,
            'attachment_type' => AttachmentTypeParameter::class,
            'group' => GroupParameter::class,
            'measurement_unit' => MeasurementUnitParameter::class,
            'currency' => Currency::class,
            default => throw new \InvalidArgumentException('Invalid parameter type: '.$type),
        };
    }

    #[Route(path: '/parts/search/{query}', name: 'typeahead_parts')]
    public function parts(EntityManagerInterface $entityManager, PartPreviewGenerator $previewGenerator,
    AttachmentURLGenerator $attachmentURLGenerator, string $query = ""): JsonResponse
    {
        $this->denyAccessUnlessGranted('@parts.read');

        $repo = $entityManager->getRepository(Part::class);

        $parts = $repo->autocompleteSearch($query, 100);

        $data = [];
        foreach ($parts as $part) {
            //Determine the picture to show:
            $preview_attachment = $previewGenerator->getTablePreviewAttachment($part);
            if($preview_attachment instanceof Attachment) {
                $preview_url = $attachmentURLGenerator->getThumbnailURL($preview_attachment, 'thumbnail_sm');
            } else {
                $preview_url = '';
            }

            /** @var Part $part */
            $data[] = [
                'id' => $part->getID(),
                'name' => $part->getName(),
                'category' => $part->getCategory() instanceof Category ? $part->getCategory()->getName() : 'Unknown',
                'footprint' => $part->getFootprint() instanceof Footprint ? $part->getFootprint()->getName() : '',
                'description' => mb_strimwidth($part->getDescription(), 0, 127, '...'),
                'image' => $preview_url,
                ];
        }

        return new JsonResponse($data);
    }

    #[Route(path: '/parameters/{type}/search/{query}', name: 'typeahead_parameters', requirements: ['type' => '.+'])]
    public function parameters(string $type, EntityManagerInterface $entityManager, string $query = ""): JsonResponse
    {
        $class = $this->typeToParameterClass($type);

        $test_obj = new $class();
        //Ensure user has the correct permissions
        $this->denyAccessUnlessGranted('read', $test_obj);

        /** @var ParameterRepository $repository */
        $repository = $entityManager->getRepository($class);

        $data = $repository->autocompleteParamName($query);

        return new JsonResponse($data);
    }

    #[Route(path: '/tags/search/{query}', name: 'typeahead_tags', requirements: ['query' => '.+'])]
    public function tags(string $query, TagFinder $finder): JsonResponse
    {
        $this->denyAccessUnlessGranted('@parts.read');

        $array = $finder->searchTags($query);

        $normalizers = [
            new ObjectNormalizer(),
        ];
        $encoders = [
            new JsonEncoder(),
        ];

        $serializer = new Serializer($normalizers, $encoders);
        $data = $serializer->serialize($array, 'json');

        return new JsonResponse($data, Response::HTTP_OK, [], true);
    }
}
