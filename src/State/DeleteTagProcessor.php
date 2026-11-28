<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Parts\Tag;
use App\Repository\Parts\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Deletes a tag through the API, but only if it is not assigned to any part, like on the admin pages.
 *
 * Unlike the other data structures, the parts refer to their tags through a join table, whose rows the database would
 * delete with the tag. These assignments would not be recorded in the history, so they could not be restored by undoing
 * the deletion.
 *
 * @implements ProcessorInterface<Tag, null>
 */
final readonly class DeleteTagProcessor implements ProcessorInterface
{
    /**
     * @param  ProcessorInterface<Tag, null>  $removeProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof Tag) {
            /** @var TagRepository $repo */
            $repo = $this->entityManager->getRepository(Tag::class);
            $count = $repo->getPartsCount($data);
            if ($count > 0) {
                throw new ConflictHttpException(sprintf(
                    'The tag "%s" is still assigned to %d part(s) and cannot be deleted. Remove it from those parts first.',
                    $data->getName(),
                    $count
                ));
            }
        }

        return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
    }
}
