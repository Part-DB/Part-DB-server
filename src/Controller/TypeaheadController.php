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

use App\DataTables\Filters\PartSearchFilter;
use App\DataTables\Filters\PartSearchSort;
use App\Entity\Attachments\Attachment;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parameters\AttachmentTypeParameter;
use App\Entity\Parameters\CategoryParameter;
use App\Entity\Parameters\FootprintParameter;
use App\Entity\Parameters\GroupParameter;
use App\Entity\Parameters\ManufacturerParameter;
use App\Entity\Parameters\MeasurementUnitParameter;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parameters\ProjectParameter;
use App\Entity\Parameters\StorageLocationParameter;
use App\Entity\Parameters\SupplierParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Currency;
use App\Exceptions\InvalidRegexException;
use App\Repository\ParameterRepository;
use App\Repository\PartRepository;
use App\Services\AI\AIPlatformRegistry;
use App\Services\AI\AIPlatforms;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\Attachments\BuiltinAttachmentsFinder;
use App\Services\Attachments\PartPreviewGenerator;
use App\Services\Tools\TagFinder;
use App\Services\Trees\StructuralElementPathCache;
use App\Settings\MiscSettings\IpnSuggestSettings;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\AI\Platform\Capability;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * In this controller the endpoints for the typeaheads are collected.
 * @phpstan-import-type AutocompletePartData from PartRepository
 */
#[Route(path: '/typeahead')]
class TypeaheadController extends AbstractController
{
    public function __construct(
        protected AttachmentURLGenerator $urlGenerator,
        protected Packages $assets,
        protected IpnSuggestSettings $ipnSuggestSettings,
        protected StructuralElementPathCache $pathCache,
    ) {
    }

    #[Route(path: '/builtInResources/search', name: 'typeahead_builtInRessources')]
    public function builtInResources(Request $request, BuiltinAttachmentsFinder $finder): JsonResponse
    {
        //Ensure that the user can access Part-DB at all
        $this->denyAccessUnlessGranted('HAS_ACCESS_PERMISSIONS');

        $query = $request->query->getString('query');
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
     * @return class-string<AbstractParameter>
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
    public function parts(
        Request $request,
        EntityManagerInterface $entityManager,
        PartPreviewGenerator $previewGenerator,
        AttachmentURLGenerator $attachmentURLGenerator,
        string $query = ""
    ): JsonResponse {
        $this->denyAccessUnlessGranted('@parts.read');

        $repo = $entityManager->getRepository(Part::class);

        //The ordering of the results chosen in the search dropdown (null orders by name)
        $sort = PartSearchSort::fromRequest($request);
        $direction = PartSearchSort::directionFromRequest($request);

        //If search options (fields, regex, extensive, wildcard) are passed, search like the search page does,
        //otherwise (e.g. part select fields) use the simple and fast autocomplete search
        if ($request->query->count() > 0) {
            $filter = PartSearchFilter::fromRequest($request, $query);

            //A regex is often incomplete while it is typed (e.g. "lm("), so return no results instead of an error
            if ($filter->isRegex() && @preg_match('~' . str_replace('~', '\\~', $query) . '~u', '') === false) {
                return new JsonResponse([]);
            }

            try {
                $parts = $repo->autocompleteSearchWithFilter($filter, 100, $sort, $direction);
            } catch (InvalidRegexException|DBALException) {
                return new JsonResponse([]);
            }
        } else { // Use the fast and simple autocomplete search for the part select fields
            $parts = $repo->autocompleteSearch($query, 100, $sort, $direction);
        }

        $data = [];
        foreach ($parts as $part) {
            //Determine the picture to show:
            $preview_attachment = $previewGenerator->getFirstValidPicture(...$part['preview_attachments']);
            if ($preview_attachment instanceof Attachment) {
                $preview_url = $attachmentURLGenerator->getThumbnailURL($preview_attachment, 'thumbnail_sm') ?? '';
            } else {
                $preview_url = '';
            }

            $data[] = [
                'id' => $part['id'],
                'name' => $part['name'],
                'category' => $part['category'] ?? 'Unknown',
                'footprint' => $part['footprint'] ?? '',
                'description' => mb_strimwidth($part['description'], 0, 127, '...'),
                'image' => $preview_url,
                //The value the results are ordered by, if it is not shown anyway (like the name and the category)
                'sort_value' => $this->getSortValue($part, $sort),
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Returns the value of the given part, which the search results are ordered by, so it can be shown next to it.
     * Empty for the orderings whose value is part of every result anyway and for parts without that value.
     * @param  AutocompletePartData  $part
     */
    private function getSortValue(array $part, ?PartSearchSort $sort): string
    {
        return match ($sort) {
            PartSearchSort::MANUFACTURER => $part['manufacturer'] ?? '',
            PartSearchSort::SUPPLIER => implode(', ', $part['suppliers']),
            PartSearchSort::ADDED_DATE => $part['addedDate']?->format('Y-m-d') ?? '',
            PartSearchSort::LAST_MODIFIED => $part['lastModified']?->format('Y-m-d') ?? '',
            PartSearchSort::TOP_CATEGORY => $part['category_id'] !== null
                ? ($this->pathCache->getPathNames(Category::class, $part['category_id'])[0] ?? '')
                : '',
            default => '',
        };
    }

    #[Route(path: '/parameters/{type}/search/{query}', name: 'typeahead_parameters', requirements: ['type' => '.+'])]
    public function parameters(string $type, EntityManagerInterface $entityManager, string $query = ""): JsonResponse
    {
        $class = $this->typeToParameterClass($type);

        $test_obj = new $class();
        //Ensure user has the correct permissions
        $this->denyAccessUnlessGranted('read', $test_obj);

        /** @var ParameterRepository<AbstractParameter> $repository */
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

    #[Route(path: '/parts/ipn-suggestions', name: 'ipn_suggestions', methods: ['GET'])]
    public function ipnSuggestions(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $partId = $request->query->get('partId');
        if ($partId === '0' || $partId === 'undefined' || $partId === 'null') {
            $partId = null;
        }
        $categoryId = $request->query->getInt('categoryId');
        $description = base64_decode($request->query->getString('description'), true);

        /** @var Part $part */
        $part = $partId !== null ? $entityManager->getRepository(Part::class)->find($partId) : new Part();
        /** @var Category|null $category */
        $category = $entityManager->getRepository(Category::class)->find($categoryId);

        //Ensure the user has access to both the part and the category
        $this->denyAccessUnlessGranted('read', $part);
        if ($category !== null) {
            $this->denyAccessUnlessGranted('read', $category);
        }

        $clonedPart = clone $part;
        $clonedPart->setCategory($category);


        $partRepository = $entityManager->getRepository(Part::class);
        $ipnSuggestions = $partRepository->autoCompleteIpn($clonedPart, $description,
            $this->ipnSuggestSettings->suggestPartDigits);

        return new JsonResponse($ipnSuggestions);
    }

    #[Route(path: '/ai/{platform}/models', name: 'typeahead_ai_models', requirements: ['platform' => '.+'])]
    public function aiModels(
        AIPlatforms $platform,
        Request $request,
        AIPlatformRegistry $platformRegistry,
        CacheInterface $cache,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('@config.change_system_settings');

        $capability_filter = $request->query->getEnum('capability', Capability::class);

        $models = $cache->get('ai_models_'.$platform->value.'_'.($capability_filter->value ?? 'all'),
            function (ItemInterface $item) use ($platformRegistry, $platform, $capability_filter) {
                $item->expiresAfter(3600); //Cache for 1 hour
                if ($capability_filter === null) {
                    return $platformRegistry->getPlatform($platform)->getModelCatalog()->getModels();
                }

                //Otherwise filter the models by the capability
                return array_filter($platformRegistry->getPlatform($platform)->getModelCatalog()->getModels(),
                    static fn(array $model) => in_array($capability_filter, $model['capabilities'], true)
                );
            });

        return new JsonResponse($models);
    }
}
