<?php
/**
 * Created by PhpStorm.
 * User: janhb
 * Date: 23.02.2019
 * Time: 19:40
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Supplier
 * @package App\Entity
 *
 * @ORM\Entity()
 * @ORM\Table("suppliers")
 */
class Supplier extends Company
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
     * @ORM\OneToMany(targetEntity="Orderdetail", mappedBy="supplier")
     */
    protected $orderdetails;

    /**
     *  Get all parts from this element
     *
     * @return int        all parts in a one-dimensional array of Part objects
     *
     * @throws Exception    if there was an error
     */
    public function getCountOfPartsToOrder() : int
    {
        /*
        $query =    'SELECT COUNT(*) as count FROM parts '.
            'LEFT JOIN device_parts ON device_parts.id_part = parts.id '.
            'LEFT JOIN devices ON devices.id = device_parts.id_device '.
            'LEFT JOIN orderdetails ON orderdetails.id = parts.order_orderdetails_id '.
            'WHERE ((parts.instock < parts.mininstock) OR (parts.manual_order != false) '.
            'OR ((devices.order_quantity > 0) '.
            'AND ((devices.order_only_missing_parts = false) '.
            'OR (parts.instock - device_parts.quantity * devices.order_quantity < parts.mininstock)))) '.
            'AND (parts.order_orderdetails_id IS NOT NULL) '.
            'AND (orderdetails.id_supplier = ?)';

        $query_data = $this->database->query($query, array($this->getID()));



        return (int) $query_data[0]['count']; */


        //TODO
        throw new \Exception("Not implemented yet!");
    }


    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'L' . sprintf('%06d', $this->getID());
    }

}