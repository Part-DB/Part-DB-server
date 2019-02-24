<?php
/**
 * Created by PhpStorm.
 * User: janhb
 * Date: 23.02.2019
 * Time: 19:02
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
     *
     * @param bool $absolute If set to true, then the absolute filename (from system root) is returned.
     * If set to false, then the path relative to Part-DB folder is returned.
     * @return string   @li the absolute path to the picture (from filesystem root), as a UNIX path (with slashes)
     * @li an empty string if there is no picture
     */
    public function getFilename(bool $absolute = true) : string
    {
        if ($absolute == true) {
            //TODO
            throw new \Exception("Not Implemented yet...");
            //return str_replace('%BASE%', BASE, $this->db_data['filename']);
        } else {
            return $this->filename;
        }
    }

    /**
     *   Get the filename of the 3d model (absolute path from filesystem root)
     *
     * @param bool $absolute If set to true, then the absolute filename (from system root) is returned.
     * If set to false, then the path relative to Part-DB folder is returned.
     *
     * @return string   @li the absolute path to the model (from filesystem root), as a UNIX path (with slashes)
     *                  @li an empty string if there is no model
     */
    public function get3dFilename(bool $absolute = true) : string
    {
        if ($absolute == true) {
            //TODO
            throw new \Exception("Not Implemented yet...");
            //return str_replace('%BASE%', BASE, $this->db_data['filename_3d']);
        } else {
            return $this->filename_3d;
        }
    }

    /**
     *  Check if the filename of this footprint is valid (picture exists)
     *
     * This method is used to get all footprints with broken filename
     * (Footprint::get_broken_filename_footprints()).
     *
     * @note An empty filename is a valid filename.
     *
     * @return boolean      @li true if file exists or filename is empty
     *                      @li false if there is no file with this filename
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
     * @note An empty filename is a valid filename.
     *
     * @return boolean      @li true if file exists or filename is empty
     *                      @li false if there is no file with this filename
     */
    public function is3dFilenameValid() : bool
    {
        if (empty($this->get3dFilename())) {
            return true;
        }

        //Check if file is X3D-Model (these has .x3d extension)
        if (strpos($this->get3dFilename(), '.x3d') == false) {
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
     * @note    The filename won't be checked if it is valid.
     *          It's not really a Problem if there is no such file...
     *          (For this purpose we have the method Footprint::get_broken_filename_footprints())
     *
     * @param string $new_filename      @li the new filename (absolute path from filesystem root, as a UNIX path [only slashes!] !! )
     *                                  @li see also lib.functions.php::to_unix_path()
     *
     * @warning     It's really important that you pass the whole (UNIX) path from filesystem root!
     *              If the file is located in the base directory of Part-DB, the base path
     *              will be automatically replaced with a placeholder before write it in the database.
     *              This way, the filenames are still correct if the installation directory
     *              of Part-DB is moved.
     *
     * @note        The path-replacing will be done in Footprint::check_values_validity(), not here.
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