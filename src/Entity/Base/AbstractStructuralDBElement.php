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

namespace App\Entity\Base;

use App\Entity\Attachments\Attachment;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Contracts\StructuralElementInterface;
use App\Entity\Contracts\HasParametersInterface;
use App\Repository\StructuralDBElementRepository;
use App\EntityListeners\TreeCacheInvalidationListener;
use App\Validator\Constraints\UniqueObjectCollection;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Parameters\ParametersTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * All elements with the fields "id", "name" and "parent_id" (at least).
 *
 * This class is for managing all database objects with a structural design.
 * All these sub-objects must have the table columns 'id', 'name' and 'parent_id' (at least)!
 * The root node has always the ID '0'.
 * It's allowed to have instances of root elements, but if you try to change
 * an attribute of a root element, you will get an exception!
 *
 *
 * @see \App\Tests\Entity\Base\AbstractStructuralDBElementTest
 *
 * @template AT of Attachment
 * @template PT of AbstractParameter
 * @template-use ParametersTrait<PT>
 * @extends AttachmentContainingDBElement<AT>
 * @uses ParametersTrait<PT>
 */
#[UniqueEntity(fields: ['name', 'parent'], message: 'structural.entity.unique_name', ignoreNull: false)]
#[ORM\MappedSuperclass(repositoryClass: StructuralDBElementRepository::class)]
#[ORM\EntityListeners([TreeCacheInvalidationListener::class])]
abstract class AbstractStructuralDBElement extends AttachmentContainingDBElement implements StructuralElementInterface, HasParametersInterface
{
    use ParametersTrait;
    use StructuralElementTrait;

    /**
     * Mapping done in subclasses.
     *
     * @var Collection<int, AbstractParameter>
     * @phpstan-var Collection<int, PT>
     */
    #[Assert\Valid]
    #[UniqueObjectCollection(fields: ['name', 'group', 'element'])]
    protected Collection $parameters;

    public function __construct()
    {
        parent::__construct();
        $this->initializeStructuralElement();
        $this->parameters = new ArrayCollection();
    }

    public function __clone()
    {
        if ($this->id) {
            //Deep clone parameters
            $parameters = $this->parameters;
            $this->parameters = new ArrayCollection();
            foreach ($parameters as $parameter) {
                $this->addParameter(clone $parameter);
            }
        }
        parent::__clone();
    }
}
