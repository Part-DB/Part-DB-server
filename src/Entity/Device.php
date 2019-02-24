<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class AttachmentType
 * @ORM\Entity()
 * @ORM\Table(name="devices")
 */
class Device extends PartsContainingDBElement
{

    /**
     * @ORM\OneToMany(targetEntity="Category", mappedBy="parent")
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Category", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @var int
     * @ORM\Column(type="integer")
     *
     */
    protected $order_quantity;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $order_only_missing_parts;

    /**
     * @ORM\OneToMany(targetEntity="DevicePart", mappedBy="device")
     */
    protected $parts;

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     *  Get the order quantity of this device
     *
     * @return integer      the order quantity
     */
    public function getOrderQuantity() : int
    {
        return $this->order_quantity;
    }

    /**
     *  Get the "order_only_missing_parts" attribute
     *
     * @return boolean      the "order_only_missing_parts" attribute
     */
    public function getOrderOnlyMissingParts() : bool
    {
        return $this->order_only_missing_parts;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     *  Set the order quantity
     *
     * @param integer $new_order_quantity       the new order quantity
     */
    public function setOrderQuantity(int $new_order_quantity) : self
    {
        if($new_order_quantity < 0)
        {
            throw new \InvalidArgumentException("The new order quantity must not be negative!");
        }
        $this->order_quantity = $new_order_quantity;
        return $this;
    }

    /**
     *  Set the "order_only_missing_parts" attribute
     *
     * @param boolean $new_order_only_missing_parts       the new "order_only_missing_parts" attribute
     *
     */
    public function setOrderOnlyMissingParts(bool $new_order_only_missing_parts) : self
    {
        $this->order_only_missing_parts = $new_order_only_missing_parts;
        return $this;
    }


    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'D' . sprintf('%09d', $this->getID());
    }
}