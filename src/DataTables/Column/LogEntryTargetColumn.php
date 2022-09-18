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

use App\Entity\Attachments\Attachment;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\UserNotAllowedLogEntry;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\PartLot;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Exceptions\EntityNotSupportedException;
use App\Repository\LogEntryRepository;
use App\Services\ElementTypeNameGenerator;
use App\Services\EntityURLGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class LogEntryTargetColumn extends AbstractColumn
{
    protected EntityManagerInterface $em;
    protected LogEntryRepository $entryRepository;
    protected EntityURLGenerator $entityURLGenerator;
    protected ElementTypeNameGenerator $elementTypeNameGenerator;
    protected TranslatorInterface $translator;

    public function __construct(EntityManagerInterface $entityManager, EntityURLGenerator $entityURLGenerator,
        ElementTypeNameGenerator $elementTypeNameGenerator, TranslatorInterface $translator)
    {
        $this->em = $entityManager;
        $this->entryRepository = $entityManager->getRepository(AbstractLogEntry::class);

        $this->entityURLGenerator = $entityURLGenerator;
        $this->elementTypeNameGenerator = $elementTypeNameGenerator;
        $this->translator = $translator;
    }

    /**
     * @param $value
     * @return mixed
     */
    public function normalize($value)
    {
        return $value;
    }

    public function configureOptions(OptionsResolver $resolver): self
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('show_associated', true);
        $resolver->setDefault('showAccessDeniedPath', true);

        return $this;
    }

    public function render($value, $context): string
    {
        if ($context instanceof UserNotAllowedLogEntry && $this->options['showAccessDeniedPath']) {
            return htmlspecialchars($context->getPath());
        }

        /** @var AbstractLogEntry $context */
        $target = $this->entryRepository->getTargetElement($context);

        $tmp = '';

        //The element is existing
        if ($target instanceof NamedElementInterface && !empty($target->getName())) {
            try {
                $tmp = sprintf(
                    '<a href="%s">%s</a>',
                    $this->entityURLGenerator->infoURL($target),
                    $this->elementTypeNameGenerator->getTypeNameCombination($target, true)
                );
            } catch (EntityNotSupportedException $exception) {
                $tmp = $this->elementTypeNameGenerator->getTypeNameCombination($target, true);
            }
        } elseif ($target instanceof AbstractDBElement) { //Target does not have a name
            $tmp = sprintf(
                '<i>%s</i>: %s',
                $this->elementTypeNameGenerator->getLocalizedTypeLabel($target),
                $target->getID()
            );
        } elseif (null === $target && $context->hasTarget()) {  //Element was deleted
            $tmp = sprintf(
                '<i>%s</i>: %s [%s]',
                $this->elementTypeNameGenerator->getLocalizedTypeLabel($context->getTargetClass()),
                $context->getTargetID(),
                $this->translator->trans('log.target_deleted')
            );
        }

        //Add a hint to the associated element if possible
        if (null !== $target && $this->options['show_associated']) {
            if ($target instanceof Attachment && null !== $target->getElement()) {
                $on = $target->getElement();
            } elseif ($target instanceof AbstractParameter && null !== $target->getElement()) {
                $on = $target->getElement();
            } elseif ($target instanceof PartLot && null !== $target->getPart()) {
                $on = $target->getPart();
            } elseif ($target instanceof Orderdetail && null !== $target->getPart()) {
                $on = $target->getPart();
            } elseif ($target instanceof Pricedetail && null !== $target->getOrderdetail() && null !== $target->getOrderdetail()->getPart()) {
                $on = $target->getOrderdetail()->getPart();
            }

            if (isset($on) && is_object($on)) {
                try {
                    $tmp .= sprintf(
                        ' (<a href="%s">%s</a>)',
                        $this->entityURLGenerator->infoURL($on),
                        $this->elementTypeNameGenerator->getTypeNameCombination($on, true)
                    );
                } catch (EntityNotSupportedException $exception) {
                    $tmp .= ' ('.$this->elementTypeNameGenerator->getTypeNameCombination($target, true).')';
                }
            }
        }

        //Log is not associated with an element
        return $tmp;
    }
}
