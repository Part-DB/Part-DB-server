<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Controller;

use App\Services\Attachments\BuiltinAttachmentsFinder;
use App\Services\TagFinder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * In this controller the endpoints for the typeaheads are collected.
 *
 * @Route("/typeahead")
 */
class TypeaheadController extends AbstractController
{
    /**
     * @Route("/builtInResources/search/{query}", name="typeahead_builtInRessources", requirements={"query"= ".+"})
     * @param  Request  $request
     * @param  string  $query
     * @param  BuiltinAttachmentsFinder  $finder
     * @return JsonResponse
     */
    public function builtInResources(Request $request, string $query, BuiltinAttachmentsFinder $finder)
    {
        $array = $finder->find($query);

        $normalizers = [
            new ObjectNormalizer(),
        ];
        $encoders = [
            new JsonEncoder(),
        ];
        $serializer = new Serializer($normalizers, $encoders);
        $data = $serializer->serialize($array, 'json');

        return new JsonResponse($data, 200, [], true);
    }

    /**
     * @Route("/tags/search/{query}", name="typeahead_tags", requirements={"query"= ".+"})
     * @param  string  $query
     * @param  TagFinder  $finder
     * @return JsonResponse
     */
    public function tags(string $query, TagFinder $finder)
    {
        $array = $finder->searchTags($query);

        $normalizers = [
            new ObjectNormalizer(),
        ];
        $encoders = [
            new JsonEncoder(),
        ];
        $serializer = new Serializer($normalizers, $encoders);
        $data = $serializer->serialize($array, 'json');

        return new JsonResponse($data, 200, [], true);
    }
}
