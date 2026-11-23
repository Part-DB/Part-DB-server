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

namespace App\Services\Tools;

use App\Entity\Parts\Tag;
use App\Repository\Parts\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function mb_strlen;

/**
 * A service related for searching for tags. Mostly useful for autocomplete reasons.
 */
class TagFinder
{
    public function __construct(protected EntityManagerInterface $em)
    {
    }

    /**
     * Search tags that begins with the certain keyword.
     *
     * @param string $keyword The keyword the tag must begin with
     * @param array  $options Some options specifying the search behavior. See configureOptions for possible options.
     *
     * @return string[] an array containing the tags that match the given keyword
     */
    public function searchTags(string $keyword, array $options = []): array
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $options = $resolver->resolve($options);

        //If the keyword is too short we will get too much results, which takes too much time...
        if (mb_strlen($keyword) < $options['min_keyword_length']) {
            return [];
        }

        /** @var TagRepository $repo */
        $repo = $this->em->getRepository(Tag::class);

        return $repo->findNamesStartingWith($keyword, $options['return_limit']);
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'return_limit' => 75,
            'min_keyword_length' => 2,
        ]);
    }
}
