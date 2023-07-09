<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Services\InfoProviderSystem;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\ManufacturingStatus;
use App\Entity\Parts\Part;
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;

/**
 * This class converts DTOs to entities which can be persisted in the DB
 */
class DTOtoEntityConverter
{

    public function convertParameter(ParameterDTO $dto, PartParameter $entity = new PartParameter()): PartParameter
    {
        $entity->setName($dto->name);
        $entity->setValueText($dto->value_text ?? '');
        $entity->setValueTypical($dto->value_typ);
        $entity->setValueMin($dto->value_min);
        $entity->setValueMax($dto->value_max);
        $entity->setUnit($dto->unit ?? '');
        $entity->setSymbol($dto->symbol ?? '');
        $entity->setGroup($dto->group ?? '');

        return $entity;
    }

    public function convertFile(FileDTO $dto, PartAttachment $entity = new PartAttachment()): PartAttachment
    {
        $entity->setURL($dto->url);

        //If no name is given, try to extract the name from the URL
        if (empty($dto->name)) {
            $entity->setName(basename($dto->url));
        } else {
            $entity->setName($dto->name);
        }

        return $entity;
    }

    /**
     * Converts a PartDetailDTO to a Part entity
     * @param  PartDetailDTO  $dto
     * @param  Part  $entity The part entity to fill
     * @return Part
     */
    public function convertPart(PartDetailDTO $dto, Part $entity = new Part()): Part
    {
        $entity->setName($dto->name);
        $entity->setDescription($dto->description ?? '');
        $entity->setComment($dto->notes ?? '');

        $entity->setManufacturerProductNumber($dto->mpn ?? '');
        $entity->setManufacturingStatus($dto->manufacturing_status ?? ManufacturingStatus::NOT_SET);

        //Add parameters
        foreach ($dto->parameters ?? [] as $parameter) {
            $entity->addParameter($this->convertParameter($parameter));
        }

        //Add datasheets
        foreach ($dto->datasheets ?? [] as $datasheet) {
            $entity->addAttachment($this->convertFile($datasheet));
        }

        return $entity;
    }

}