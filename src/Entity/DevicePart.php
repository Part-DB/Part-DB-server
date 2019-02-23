<?php
/**
 * Created by PhpStorm.
 * User: janhb
 * Date: 23.02.2019
 * Time: 18:55
 */

namespace App\Entity;


use Doctrine\ORM\Mapping as ORM;

/**
 * Class DevicePart
 * @package App\Entity
 *
 * @ORM\Table("device_parts")
 * @ORM\Entity()
 */
class DevicePart extends DBElement
{
    /**
     * @var Device
     * @ORM\ManyToOne(targetEntity="Device", inversedBy="parts")
     * @ORM\JoinColumn(name="id_device", referencedColumnName="id")
     */
    protected $device;

    //TODO
    protected $part;

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'DP' . sprintf('%06d', $this->getID());
    }
}