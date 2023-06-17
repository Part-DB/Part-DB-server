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

use App\Entity\Attachments\Attachment;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\UserNotAllowedLogEntry;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\PartLot;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Exceptions\EntityNotSupportedException;
use App\Repository\LogEntryRepository;
use App\Services\ElementTypeNameGenerator;
use App\Services\EntityURLGenerator;
use App\Services\LogSystem\LogTargetHelper;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class LogEntryTargetColumn extends AbstractColumn
{
    public function __construct(private readonly LogTargetHelper $logTargetHelper)
    {
    }

    /**
     * @param $value
     * @return mixed
     */
    public function normalize($value): mixed
    {
        return $value;
    }

    /**
     * @return $this
     */
    public function configureOptions(OptionsResolver $resolver): self
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('show_associated', true);
        $resolver->setDefault('showAccessDeniedPath', true);

        return $this;
    }

    public function render($value, $context): string
    {
        return $this->logTargetHelper->formatTarget($context, [
            'showAccessDeniedPath' => $this->options['showAccessDeniedPath'],
            'show_associated' => $this->options['show_associated'],
        ]);
    }
}
