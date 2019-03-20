<?php declare(strict_types=1);

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


namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Footprint
 * @package App\Entity
 *
 * @ORM\Entity()
 * @ORM\Table("footprints")
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
     * @ORM\Column(type="string")
     */
    protected $filename;

    /**
     * @ORM\OneToMany(targetEntity="Part", mappedBy="footprint")
     */
    protected $parts;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $filename_3d;

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'F' . sprintf('%06d', $this->getID());
    }

    /****************************************
     * Getters
     ****************************************/

    /**
     * Get the filename of the picture (absolute path from filesystem root)
     * @return string   the saved filename in the DB
     * * an empty string if there is no picture
     */
    public function getFilename() : string
    {
        return $this->filename;
    }

    /**
     *   Get the filename of the 3d model (absolute path from filesystem root)
     *
     * @param bool $absolute If set to true, then the absolute filename (from system root) is returned.
     * If set to false, then the path relative to Part-DB folder is returned.
     *
     * @return string   * the absolute path to the model (from filesystem root), as a UNIX path (with slashes)
     *                  * an empty string if there is no model
     */
    public function get3dFilename(bool $absolute = true) : string
    {
        if ($absolute === true) {
            //TODO
            throw new \Exception('Not Implemented yet...');
            //return str_replace('%BASE%', BASE, $this->db_data['filename_3d']);
        }

        return $this->filename_3d;
    }

    /**
     *  Check if the filename of this footprint is valid (picture exists)
     *
     * This method is used to get all footprints with broken filename
     * (Footprint::get_broken_filename_footprints()).
     *
     *  An empty filename is a valid filename.
     *
     * @return boolean      * true if file exists or filename is empty
     *                      * false if there is no file with this filename
     */
    public function isFilenameValid() : bool
    {
        if (empty($this->getFilename())) {
            return true;
        }

        return file_exists($this->getFilename());
    }

    /**
     *  Check if the filename of this 3d footprint is valid (model exists and have )
     *
     * This method is used to get all footprints with broken 3d filename
     * (Footprint::get_broken_3d_filename_footprints()).
     *
     *  An empty filename is a valid filename.
     *
     * @return boolean      * true if file exists or filename is empty
     *                      * false if there is no file with this filename
     */
    public function is3dFilenameValid() : bool
    {
        if (empty($this->get3dFilename())) {
            return true;
        }

        //Check if file is X3D-Model (these has .x3d extension)
        if (strpos($this->get3dFilename(), '.x3d') === false) {
            return false;
        }

        return file_exists($this->get3dFilename());
    }

    /*****************************************************************************
     * Setters
     ****************************************************************************/

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     *  Change the filename of this footprint
     *
     *     The filename won't be checked if it is valid.
     *          It's not really a Problem if there is no such file...
     *          (For this purpose we have the method Footprint::get_broken_filename_footprints())
     *
     * @param string $new_filename      * the new filename (absolute path from filesystem root, as a UNIX path [only slashes!] !! )
     *                                  * see also lib.functions.php::to_unix_path()
     *
     *      It's really important that you pass the whole (UNIX) path from filesystem root!
     *              If the file is located in the base directory of Part-DB, the base path
     *              will be automatically replaced with a placeholder before write it in the database.
     *              This way, the filenames are still correct if the installation directory
     *              of Part-DB is moved.
     *
     *         The path-replacing will be done in Footprint::check_values_validity(), not here.
     *
     * @throws Exception if there was an error
     */
    public function setFilename(string $new_filename) : self
    {
        $this->filename = $new_filename;
        return $this;
    }

    /**
     *  Change the 3d model filename of this footprint
     * @throws Exception if there was an error
     */
    public function set3dFilename(string $new_filename) : self
    {
        $this->filename = $new_filename;
        return $this;
    }
}