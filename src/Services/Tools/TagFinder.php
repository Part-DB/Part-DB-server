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

use App\Entity\Parts\Part;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function mb_strlen;
use function array_slice;

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
        $results = [];
        $keyword_regex = '/^'.preg_quote($keyword, '/').'/';

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $options = $resolver->resolve($options);

        //If the keyword is too short we will get too much results, which takes too much time...
        if (mb_strlen($keyword) < $options['min_keyword_length']) {
            return [];
        }

        //Build a query to get all
        $qb = $this->em->createQueryBuilder();

        $qb->select('p.tags')
            ->from(Part::class, 'p')
            ->where('ILIKE(p.tags, ?1) = TRUE')
            ->setMaxResults($options['query_limit'])
            //->orderBy('RAND()')
            ->setParameter(1, '%'.$keyword.'%');

        $possible_tags = $qb->getQuery()->getArrayResult();

        //Iterate over each possible tags (which are comma separated) and extract tags which match our keyword
        foreach ($possible_tags as $tags) {
            $tags = explode(',', (string) $tags['tags']);
            $results = array_merge($results, preg_grep($keyword_regex, $tags));
        }

        $results = array_unique($results);
        //Limit the returned tag count to specified value.
        return array_slice($results, 0, $options['return_limit']);
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'query_limit' => 75,
            'return_limit' => 75,
            'min_keyword_length' => 2,
        ]);
    }
}
