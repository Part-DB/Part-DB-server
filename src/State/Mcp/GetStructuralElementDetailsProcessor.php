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
use App\Entity\Base\AbstractStructuralDBElement;
use App\Mcp\DTO\ElementByIdInput;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Generic get-by-id processor shared by all structural "master data" entities (categories, footprints,
 * manufacturers, storage locations, suppliers, measurement units, part custom states). The concrete entity
 * class is determined from the operation, so this single processor can be reused for all of them.
 */
class GetStructuralElementDetailsProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AbstractStructuralDBElement
    {
        if (!$data instanceof ElementByIdInput) {
            throw new \InvalidArgumentException('Expected ElementByIdInput');
        }

        $class = $operation->getClass();
        if (!is_a($class, AbstractStructuralDBElement::class, true)) {
            throw new \LogicException(sprintf('%s can only be used for resources extending %s', self::class, AbstractStructuralDBElement::class));
        }

        $element = $this->entityManager->find($class, $data->id);

        if (!$element instanceof AbstractStructuralDBElement) {
            throw new NotFoundHttpException(sprintf('%s with id %d not found', (new \ReflectionClass($class))->getShortName(), $data->id));
        }

        if (!$this->authorizationChecker->isGranted('read', $element)) {
            throw new AccessDeniedException(sprintf('Access denied to %s with id %d', (new \ReflectionClass($class))->getShortName(), $data->id));
        }

        return $element;
    }
}
