<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
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
 *
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

use App\Entity\Base\PartsContainingDBElement;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Footprint.
 *
 * @ORM\Entity(repositoryClass="App\Repository\StructuralDBElementRepository")
 * @ORM\Table("`footprints`")
 */
class Footprint extends PartsContainingDBElement
{
    /**
     * @ORM\OneToMany(targetEntity="Footprint", mappedBy="parent")
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Footprint", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @var string
     * @ORM\Column(type="string", length=65536)
     */
    protected $filename;

    /**
     * @ORM\OneToMany(targetEntity="Part", mappedBy="footprint")
     */
    protected $parts;

    /**
     * @var string
     * @ORM\Column(type="string", length=65536)
     */
    protected $filename_3d;

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
     * Get the filename of the picture (absolute path from filesystem root).
     *
     * @return string the saved filename in the DB
     *                * an empty string if there is no picture
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     *   Get the filename of the 3d model (absolute path from filesystem root).
     * @return string * the absolute path to the model (from filesystem root), as a UNIX path (with slashes)
     *                * an empty string if there is no model
     */
    public function get3dFilename(): string
    {
        return $this->filename_3d;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     *  Change the filename of this footprint.
     *  @param string $new_filename The new file name
     *  @return Footprint
     */
    public function setFilename(string $new_filename): self
    {
        $this->filename = $new_filename;

        return $this;
    }

    /**
     *  Change the 3d model filename of this footprint.
     * @param string $new_filename The new filename
     *
     * @return Footprint
     */
    public function set3dFilename(string $new_filename): self
    {
        $this->filename = $new_filename;

        return $this;
    }
}
