<?php
/**
 * Created by PhpStorm.
 * User: janhb
 * Date: 23.02.2019
 * Time: 19:11
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Manufacturer
 * @package App\Entity
 *
 * @ORM\Entity()
 * @ORM\Table("manufacturers")
 */
class Manufacturer extends Company
{
    /**
     * @ORM\OneToMany(targetEntity="Manufacturer", mappedBy="parent")
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Manufacturer", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="Part", mappedBy="manufacturer")
     */
    protected $parts;

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'M' . sprintf('%06d', $this->getID());
    }
}