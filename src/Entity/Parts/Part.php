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

namespace App\Entity\Parts;

use App\Repository\PartRepository;
use Doctrine\DBAL\Types\Types;
use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\PartTraits\ProjectTrait;
use App\Entity\Parameters\ParametersTrait;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\PartTraits\AdvancedPropertyTrait;
use App\Entity\Parts\PartTraits\BasicPropertyTrait;
use App\Entity\Parts\PartTraits\InstockTrait;
use App\Entity\Parts\PartTraits\ManufacturerTrait;
use App\Entity\Parts\PartTraits\OrderTrait;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Part class.
 *
 * The class properties are split over various traits in directory PartTraits.
 * Otherwise, this class would be too big, to be maintained.
 * @see \App\Tests\Entity\Parts\PartTest
 */
#[UniqueEntity(fields: ['ipn'], message: 'part.ipn.must_be_unique')]
#[ORM\Entity(repositoryClass: PartRepository::class)]
#[ORM\Table('`parts`')]
#[ORM\Index(name: 'parts_idx_datet_name_last_id_needs', columns: ['datetime_added', 'name', 'last_modified', 'id', 'needs_review'])]
#[ORM\Index(name: 'parts_idx_name', columns: ['name'])]
#[ORM\Index(name: 'parts_idx_ipn', columns: ['ipn'])]
class Part extends AttachmentContainingDBElement
{
    use AdvancedPropertyTrait;
    //use MasterAttachmentTrait;
    use BasicPropertyTrait;
    use InstockTrait;
    use ManufacturerTrait;
    use OrderTrait;
    use ParametersTrait;
    use ProjectTrait;

    /** @var Collection<int, PartParameter>
     */
    #[Assert\Valid]
    #[Groups(['full'])]
    #[ORM\OneToMany(targetEntity: PartParameter::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => 'ASC', 'name' => 'ASC'])]
    protected Collection $parameters;


    /** *************************************************************
     * Overridden properties
     * (They are defined here and not in a trait, to avoid conflicts).
     ****************************************************************/
    /**
     * @var string The name of this part
     */
    #[ORM\Column(type: Types::STRING)]
    protected string $name = '';

    /**
     * @var Collection<int, PartAttachment>
     */
    #[Assert\Valid]
    #[Groups(['full'])]
    #[ORM\OneToMany(targetEntity: PartAttachment::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $attachments;

    /**
     * @var Attachment|null
     */
    #[Assert\Expression('value == null or value.isPicture()', message: 'part.master_attachment.must_be_picture')]
    #[ORM\ManyToOne(targetEntity: Attachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    protected ?Attachment $master_picture_attachment = null;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
        parent::__construct();
        $this->partLots = new ArrayCollection();
        $this->orderdetails = new ArrayCollection();
        $this->parameters = new ArrayCollection();
        $this->project_bom_entries = new ArrayCollection();
    }

    public function __clone()
    {
        if ($this->id) {
            //Deep clone part lots
            $lots = $this->partLots;
            $this->partLots = new ArrayCollection();
            foreach ($lots as $lot) {
                $this->addPartLot(clone $lot);
            }

            //Deep clone order details
            $orderdetails = $this->orderdetails;
            $this->orderdetails = new ArrayCollection();
            foreach ($orderdetails as $orderdetail) {
                $this->addOrderdetail(clone $orderdetail);
            }

            //Deep clone parameters
            $parameters = $this->parameters;
            $this->parameters = new ArrayCollection();
            foreach ($parameters as $parameter) {
                $this->addParameter(clone $parameter);
            }
        }
        parent::__clone();
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context, $payload)
    {
        //Ensure that the part name fullfills the regex of the category
        if ($this->category instanceof Category) {
            $regex = $this->category->getPartnameRegex();
            if ($regex !== '' && !preg_match($regex, $this->name)) {
                $context->buildViolation('part.name.must_match_category_regex')
                    ->atPath('name')
                    ->setParameter('%regex%', $regex)
                    ->addViolation();
            }
        }
    }
}
