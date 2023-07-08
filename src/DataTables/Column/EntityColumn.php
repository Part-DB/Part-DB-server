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

namespace App\DataTables\Column;

use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Services\EntityURLGenerator;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class EntityColumn extends AbstractColumn
{
    public function __construct(protected EntityURLGenerator $urlGenerator, protected PropertyAccessorInterface $accessor)
    {
    }

    /**
     * The normalize function is responsible for converting parsed and processed data to a datatables-appropriate type.
     *
     * @param mixed $value The single value of the column
     * @return mixed
     */
    public function normalize($value): mixed
    {
        /** @var AbstractNamedDBElement $value */
        return $value;
    }

    /**
     * @return $this
     */
    public function configureOptions(OptionsResolver $resolver): static
    {
        parent::configureOptions($resolver);

        $resolver->setRequired('property');

        $resolver->setDefault('field', static fn(Options $option): string => $option['property'].'.name');

        $resolver->setDefault('render', fn(Options $options) => function ($value, $context) use ($options): string {
            if ($this->accessor->isReadable($context, $options['property'])) {
                $entity = $this->accessor->getValue($context, $options['property']);
            } else {
                $entity = null;
            }

            /** @var AbstractNamedDBElement|null $entity */

            if ($entity instanceof AbstractNamedDBElement) {
                if (null !== $entity->getID()) {
                    return sprintf(
                        '<a href="%s" title="%s">%s</a>',
                        $this->urlGenerator->listPartsURL($entity),
                        $entity instanceof AbstractStructuralDBElement ? htmlspecialchars($entity->getFullPath()) : htmlspecialchars($entity->getName()),
                        htmlspecialchars($entity->getName())
                    );
                }

                return sprintf('<i>%s</i>', $value);
            }

            return '';
        });

        return $this;
    }
}
