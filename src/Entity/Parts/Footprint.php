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
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/.
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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

namespace App\Entity\Parts;

use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Parameters\FootprintParameter;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Footprint.
 *
 * @ORM\Entity(repositoryClass="App\Repository\StructuralDBElementRepository")
 * @ORM\Table("`footprints`")
 */
class Footprint extends AbstractPartsContainingDBElement
{
    /**
     * @ORM\ManyToOne(targetEntity="Footprint", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="Footprint", mappedBy="parent")
     */
    protected $children;

    /**
     * @ORM\OneToMany(targetEntity="Part", mappedBy="footprint", fetch="EXTRA_LAZY")
     */
    protected $parts;
    /**
     * @var Collection<int, FootprintAttachment>
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\FootprintAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @Assert\Valid()
     */
    protected $attachments;

    /**
     * @var FootprintAttachment|null
     * @ORM\ManyToOne(targetEntity="App\Entity\Attachments\FootprintAttachment")
     * @ORM\JoinColumn(name="id_footprint_3d", referencedColumnName="id")
     */
    protected $footprint_3d;

    /** @var Collection<int, FootprintParameter>
     * @ORM\OneToMany(targetEntity="App\Entity\Parameters\FootprintParameter", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"group" = "ASC" ,"name" = "ASC"})@ORM\OrderBy({"group" = "ASC" ,"name" = "ASC"})
     * @Assert\Valid()
     */
    protected $parameters;

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'F'.sprintf('%06d', $this->getID());
    }

    /****************************************
     * Getters
     ****************************************/

    /**
     * Returns the 3D Model associated with this footprint.
     */
    public function getFootprint3d(): ?FootprintAttachment
    {
        return $this->footprint_3d;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     * Sets the 3D Model associated with this footprint.
     *
     * @param FootprintAttachment|null $new_attachment The new 3D Model
     *
     * @return Footprint
     */
    public function setFootprint3d(?FootprintAttachment $new_attachment): self
    {
        $this->footprint_3d = $new_attachment;

        return $this;
    }
}
