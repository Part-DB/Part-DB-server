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

namespace App\DataTables\Column;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Parts\Part;
use App\Services\EntityURLGenerator;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class EntityColumn extends AbstractColumn
{
    protected $urlGenerator;
    protected $accessor;

    public function __construct(EntityURLGenerator $URLGenerator, PropertyAccessorInterface $accessor)
    {
        $this->urlGenerator = $URLGenerator;
        $this->accessor = $accessor;
    }

    /**
     * The normalize function is responsible for converting parsed and processed data to a datatables-appropriate type.
     *
     * @param mixed $value The single value of the column
     */
    public function normalize($value)
    {
        /** @var AbstractNamedDBElement $value */
        return $value;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setRequired('property');

        $resolver->setDefault('field', function (Options $option) {
            return $option['property'].'.name';
        });

        $resolver->setDefault('render', function (Options $options) {
            return function ($value, Part $context) use ($options) {
                /** @var AbstractDBElement|null $entity */
                $entity = $this->accessor->getValue($context, $options['property']);

                if (null !== $entity) {
                    if (null !== $entity->getID()) {
                        return sprintf(
                            '<a href="%s">%s</a>',
                            $this->urlGenerator->listPartsURL($entity),
                            $value
                        );
                    }

                    return sprintf('<i>%s</i>', $value);
                }

                return '';
            };
        });

        return $this;
    }
}
