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
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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
use App\Entity\LogSystem\AbstractLogEntry;
use App\Exceptions\EntityNotSupportedException;
use App\Services\ElementTypeNameGenerator;
use App\Services\EntityURLGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class LogEntryTargetColumn extends AbstractColumn
{
    protected $em;
    protected $entryRepository;
    protected $entityURLGenerator;
    protected $elementTypeNameGenerator;
    protected $translator;

    public function __construct(EntityManagerInterface $entityManager, EntityURLGenerator $entityURLGenerator,
        ElementTypeNameGenerator $elementTypeNameGenerator, TranslatorInterface $translator)
    {
        $this->em = $entityManager;
        $this->entryRepository = $entityManager->getRepository(AbstractLogEntry::class);

        $this->entityURLGenerator = $entityURLGenerator;
        $this->elementTypeNameGenerator = $elementTypeNameGenerator;
        $this->translator = $translator;
    }

    public function normalize($value)
    {
        return $value;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        return $this;
    }

    public function render($value, $context)
    {
        /** @var AbstractLogEntry $context */
        $target = $this->entryRepository->getTargetElement($context);

        //The element is existing
        if ($target instanceof AbstractNamedDBElement) {
            try {
                return sprintf(
                    '<a href="%s">%s</a>',
                    $this->entityURLGenerator->infoURL($target),
                    $this->elementTypeNameGenerator->getTypeNameCombination($target, true)
                );
            } catch (EntityNotSupportedException $exception) {
                return $this->elementTypeNameGenerator->getTypeNameCombination($target, true);
            }
        }

        //Target does not have a name
        if ($target instanceof AbstractDBElement) {
            return sprintf(
                '<i>%s</i>: %s',
                $this->elementTypeNameGenerator->getLocalizedTypeLabel($target),
                $target->getID()
            );
        }

        //Element was deleted
        if (null === $target && $context->hasTarget()) {
            return sprintf(
                '<i>%s</i>: %s [%s]',
                $this->elementTypeNameGenerator->getLocalizedTypeLabel($context->getTargetClass()),
                $context->getTargetID(),
                $this->translator->trans('log.target_deleted')
            );
        }

        //Log is not associated with an element
        return '';
    }
}
