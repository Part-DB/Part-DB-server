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
//use Webmozart\Assert\Assert;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Part
 * @package App\Entity
 *
 * @ORM\Entity(repositoryClass="App\Repository\PartRepository")
 * @ORM\Table("parts")
 */
class Part extends AttachmentContainingDBElement
{
    const INSTOCK_UNKNOWN   = -2;

    /**
     * @var Category
     * @ORM\ManyToOne(targetEntity="Category", inversedBy="parts")
     * @ORM\JoinColumn(name="id_category", referencedColumnName="id")
     */
    protected $category;

    /**
     * @var Footprint|null
     * @ORM\ManyToOne(targetEntity="Footprint", inversedBy="parts")
     * @ORM\JoinColumn(name="id_footprint", referencedColumnName="id")
     */
    protected $footprint;

    /**
     * @var Storelocation|null
     * @ORM\ManyToOne(targetEntity="Storelocation", inversedBy="parts")
     * @ORM\JoinColumn(name="id_storelocation", referencedColumnName="id")
     */
    protected $storelocation;

    /**
     * @var Manufacturer|null
     * @ORM\ManyToOne(targetEntity="Manufacturer", inversedBy="parts")
     * @ORM\JoinColumn(name="id_manufacturer", referencedColumnName="id")
     */
    protected $manufacturer;

    /**
     * @var Attachment
     * @ORM\ManyToOne(targetEntity="Attachment")
     * @ORM\JoinColumn(name="id_master_picture_attachement", referencedColumnName="id")
     */
    protected $master_picture_attachment;

    /**
     * @var
     * @ORM\OneToMany(targetEntity="Orderdetail", mappedBy="part")
     */
    protected $orderdetails;

    /**
     * @var Orderdetail
     * @ORM\OneToOne(targetEntity="Orderdetail")
     * @ORM\JoinColumn(name="order_orderdetails_id", referencedColumnName="id")
     */
    protected $order_orderdetail;

    //TODO
    protected $devices;


    /**********************
     * Propertys
     ***********************/

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $description = "";

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @Assert\GreaterThanOrEqual(0)
     */
    protected $instock = 0;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @Assert\GreaterThanOrEqual(0)
     */
    protected $mininstock = 0;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $comment = "";

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $visible = true;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $favorite = false;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $order_quantity = 0;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $manual_order = false;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $manufacturer_product_url = "";


    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'P' . sprintf('%06d', $this->getID());
    }


    /*********************************************************************************
     * Getters
     ********************************************************************************/

    /**
     * Get the description string like it is saved in the database.
     * This can contain BBCode, it is not parsed yet.
     *
     * @return string       the description
     */
    public function getDescription() : string
    {
        return  htmlspecialchars($this->description);
    }

    /**
     *  Get the count of parts which are in stock.
     *  When the instock is unkown, then Part::INSTOCK_UNKNOWN is returned.
     *
     * @return int       count of parts which are in stock
     */
    public function getInstock() : int
    {
        return $this->instock;
    }

    /**
     * Check if the value of the Instock is unknown.
     * @return bool True, if the value of the instock is unknown.
     */
    public function isInstockUnknown() : bool
    {
        return $this->instock <= static::INSTOCK_UNKNOWN;
    }/** @noinspection ReturnTypeCanBeDeclaredInspection */

    /**
     *  Get the count of parts which must be in stock at least
     *
     * @return integer       count of parts which must be in stock at least
     */
    public function getMinInstock() : int
    {
        return $this->mininstock;
    }

    /**
     *  Get the comment associated with this part.
     *
     * @return string  The raw/unparsed comment
     */
    public function getComment() : string
    {

        return htmlspecialchars($this->comment);
    }

    /**
     *  Get if this part is obsolete
     *
     *     A Part is marked as "obsolete" if all their orderdetails are marked as "obsolete".
     *          If a part has no orderdetails, the part isn't marked as obsolete.
     *
     * @return boolean  true, if this part is obsolete. false, if this part isn't obsolete
     */
    public function isObsolete() : bool
    {
        $all_orderdetails = $this->getOrderdetails();

        if (count($all_orderdetails) == 0) {
            return false;
        }

        foreach ($all_orderdetails as $orderdetails) {
            if (! $orderdetails->getObsolete()) {
                return false;
            }
        }

        return true;
    }

    /**
     *  Get if this part is visible
     *
     * @return boolean      true if this part is visible
     *                      false if this part isn't visible
     */
    public function isVisible() : bool
    {
        return $this->visible;
    }

    /**
     * Get if this part is a favorite.
     *
     * @return bool * true if this part is a favorite
     *     * false if this part is not a favorite.
     */
    public function isFavorite() : bool
    {
        return $this->favorite;
    }

    /**
     *  Get the selected order orderdetails of this part
     *
     * @return Orderdetail         the selected order orderdetails
     * @return NULL                 if there is no order supplier selected
     * @throws Exception
     */
    public function getOrderOrderdetails() : ?Orderdetail
    {
        //TODO
        /*
        if ($this->order_orderdetails->getObsolete()) {
            $this->setOrderOrderdetailsID(null);
            $this->order_orderdetails = null;
        }*/

        return $this->order_orderdetail;
    }

    /**
     *  Get the order quantity of this part
     *
     * @return integer      the order quantity
     */
    public function getOrderQuantity() : int
    {
        return $this->order_quantity;
    }

    /**
     *  Get the minimum quantity which should be ordered
     *
     * @param boolean $with_devices * if true, all parts from devices which are marked as "to order" will be included in the calculation
     * * if false, only max(mininstock - instock, 0) will be returned
     *
     * @return integer      the minimum order quantity
     * @throws Exception
     */
    public function getMinOrderQuantity(bool $with_devices = true) : int
    {
        //TODO
        throw new \Exception("Not implemented yet...");

        /**
        if ($with_devices) {
        $count_must_order = 0;      // for devices with "order_only_missing_parts == false"
        $count_should_order = 0;    // for devices with "order_only_missing_parts == true"
        $deviceparts = DevicePart::getOrderDeviceParts($this->database, $this->current_user, $this->log, $this->getID());
        foreach ($deviceparts as $devicepart) {
        /** @var $devicepart DevicePart */
        /** @var $device Device */ /**
    $device = $devicepart->getDevice();
    if ($device->getOrderOnlyMissingParts()) {
    $count_should_order += $device->getOrderQuantity() * $devicepart->getMountQuantity();
    } else {
    $count_must_order += $device->getOrderQuantity() * $devicepart->getMountQuantity();
    }
    }

    return $count_must_order + max(0, $this->getMinInstock() - $this->getInstock() + $count_should_order);
    } else {
    return max(0, $this->getMinInstock() - $this->getInstock());
    } **/
    }

    /**
     *  Check if this part is marked for manual ordering
     *
     * @return boolean      the "manual_order" attribute
     */
    public function isMarkedForManualOrder() : bool
    {
        return $this->manual_order;
    }

    /**
     * Check if the part is automatically marked for Ordering, because the instock value is smaller than the min instock value.
     * This is called automatic ordering
     * @return bool True, if the part should be ordered.
     */
    public function isAutoOrdered() : bool
    {
        //Parts with negative instock never gets ordered.
        if ($this->getInstock() < 0) {
            return false;
        }

        return $this->getInstock() < $this->getMinInstock();
    }

    /**
     *  Get the link to the website of the article on the manufacturers website
     *  When no this part has no explicit url set, then it is tried to generate one from the Manufacturer of this part
     *  automatically.
     *
     * @param
     *
     * @return string           the link to the article
     */
    public function getManufacturerProductUrl() : string
    {
        if ($this->manufacturer_product_url != '') {
            return $this->manufacturer_product_url;
        }

        if ($this->getManufacturer() !== null) {
            return $this->getManufacturer()->getAutoProductUrl($this->name);
        } else {
            return '';
        } // no url is available
    }

    /**
     * Similar to getManufacturerProductUrl, but here only the database value is returned.
     * @return string The manufacturer url saved in DB for this part.
     */
    public function getOwnProductURL() : string
    {
        return $this->manufacturer_product_url;
    }

    /**
     *  Get the category of this part
     *
     * There is always a category, for each part!
     *
     * @return Category     the category of this part
     */
    public function getCategory() : Category
    {
        return $this->category;
    }

    /**
     *  Get the footprint of this part (if there is one)
     *
     * @return Footprint    the footprint of this part (if there is one)
     * @return NULL         if this part has no footprint
     */
    public function getFootprint() : ?Footprint
    {
        return $this->footprint;
    }

    /**
     *  Get the storelocation of this part (if there is one)
     *
     * @return Storelocation    the storelocation of this part (if there is one)
     * @return NULL             if this part has no storelocation
     */
    public function getStorelocation() : ?Storelocation
    {
        return $this->storelocation;
    }

    /**
     *  Get the manufacturer of this part (if there is one)
     *
     * @return Manufacturer     the manufacturer of this part (if there is one)
     * @return NULL             if this part has no manufacturer
     */
    public function getManufacturer() : ?Manufacturer
    {
        return $this->manufacturer;
    }

    /**
     *  Get the master picture "Attachement"-object of this part (if there is one)
     *
     * @return Attachment      the master picture Attachement of this part (if there is one)
     * @return NULL             if this part has no master picture
     */
    public function getMasterPictureAttachement() : ?Attachment
    {
        return $this->master_picture_attachment;
    }

    /**
     *  Get all orderdetails of this part
     *
     * @param boolean $hide_obsolete    If true, obsolete orderdetails will NOT be returned
     *
     * @return Orderdetails[]    * all orderdetails as a one-dimensional array of Orderdetails objects
     *                      (empty array if there are no ones)
     *                  * the array is sorted by the suppliers names / minimum order quantity
     *
     * @throws Exception if there was an error
     */
    public function getOrderdetails(bool $hide_obsolete = false)
    {
        if ($hide_obsolete) {
            $orderdetails = $this->orderdetails;
            foreach ($orderdetails as $key => $details) {
                if ($details->getObsolete()) {
                    unset($orderdetails[$key]);
                }
            }
            return $orderdetails;
        } else {
            return $this->orderdetails;
        }
    }

    /**
     *  Get all devices which uses this part
     *
     * @return Device[]    * all devices which uses this part as a one-dimensional array of Device objects
     *                      (empty array if there are no ones)
     *                  * the array is sorted by the devices names
     *
     * @throws Exception if there was an error
     */
    public function getDevices() : array
    {
        return $this->devices;
    }

    /**
     *  Get all suppliers of this part
     *
     * This method simply gets the suppliers of the orderdetails and prepare them.\n
     * You can get the suppliers as an array or as a string with individual delimeter.
     *
     * @param boolean       $object_array   * if true, this method returns an array of Supplier objects
     *                                      * if false, this method returns an array of strings
     * @param string|NULL   $delimeter      * if this is a string and "$object_array == false",
     *                                          this method returns a string with all
     *                                          supplier names, delimeted by "$delimeter"
     * @param boolean       $full_paths     * if true and "$object_array = false", the returned
     *                                          suppliernames are full paths (path + name)
     *                                      * if true and "$object_array = false", the returned
     *                                          suppliernames are only the names (without path)
     * @param boolean       $hide_obsolete  If true, suppliers from obsolete orderdetails will NOT be returned
     *
     * @return array        all suppliers as a one-dimensional array of Supplier objects
     *                      (if "$object_array == true")
     * @return array        all supplier-names as a one-dimensional array of strings
     *                      ("if $object_array == false" and "$delimeter == NULL")
     * @return string       a sting of all supplier names, delimeted by $delimeter
     *                      ("if $object_array == false" and $delimeter is a string)
     *
     * @throws Exception    if there was an error
     */
    public function getSuppliers(bool $object_array = true, $delimeter = null, bool $full_paths = false, bool $hide_obsolete = false)
    {
        $suppliers = array();
        $orderdetails = $this->getOrderdetails($hide_obsolete);

        foreach ($orderdetails as $details) {
            $suppliers[] = $details->getSupplier();
        }

        if ($object_array) {
            return $suppliers;
        } else {
            $supplier_names = array();
            foreach ($suppliers as $supplier) {
                /** @var Supplier $supplier */
                if ($full_paths) {
                    $supplier_names[] = $supplier->getFullPath();
                } else {
                    $supplier_names[] = $supplier->getName();
                }
            }

            if (\is_string($delimeter)) {
                return implode($delimeter, $supplier_names);
            } else {
                return $supplier_names;
            }
        }
    }

    /**
     *  Get all supplier-part-Nrs
     *
     * This method simply gets the suppliers-part-Nrs of the orderdetails and prepare them.\n
     * You can get the numbers as an array or as a string with individual delimeter.
     *
     * @param string|NULL   $delimeter      * if this is a string, this method returns a delimeted string
     *                                      * otherwise, this method returns an array of strings
     * @param boolean       $hide_obsolete  If true, supplierpartnrs from obsolete orderdetails will NOT be returned
     *
     * @return array        all supplierpartnrs as an array of strings (if "$delimeter == NULL")
     * @return string       all supplierpartnrs as a string, delimeted ba $delimeter (if $delimeter is a string)
     *
     * @throws Exception    if there was an error
     */
    public function getSupplierPartNrs($delimeter = null, bool $hide_obsolete = false)
    {
        $supplierpartnrs = array();

        foreach ($this->getOrderdetails($hide_obsolete) as $details) {
            $supplierpartnrs[] = $details->getSupplierPartNr();
        }

        if (\is_string($delimeter)) {
            return implode($delimeter, $supplierpartnrs);
        } else {
            return $supplierpartnrs;
        }
    }

    /**
     *  Get all prices of this part
     *
     * This method simply gets the prices of the orderdetails and prepare them.\n
     * In the returned array/string there is a price for every supplier.
     *
     * @param boolean       $float_array    * if true, the returned array is an array of floats
     *                                      * if false, the returned array is an array of strings
     * @param string|NULL   $delimeter      if this is a string, this method returns a delimeted string
     *                                      instead of an array.
     * @param integer       $quantity       this is the quantity to choose the correct priceinformation
     * @param integer|NULL  $multiplier     * This is the multiplier which will be applied to every single price
     *                                      * If you pass NULL, the number from $quantity will be used
     * @param boolean       $hide_obsolete  If true, prices from obsolete orderdetails will NOT be returned
     *
     * @return array        all prices as an array of floats (if "$delimeter == NULL" & "$float_array == true")
     * @return array        all prices as an array of strings (if "$delimeter == NULL" & "$float_array == false")
     * @return string       all prices as a string, delimeted by $delimeter (if $delimeter is a string)
     *
     *              If there are orderdetails without prices, for these orderdetails there
     *                      will be a "NULL" in the returned float array (or a "-" in the string array)!!
     *                      (This is needed for the HTML output, if there are all orderdetails and prices listed.)
     *
     * @throws Exception    if there was an error
     */
    public function getPrices(bool $float_array = false, $delimeter = null, int $quantity = 1, $multiplier = null, bool $hide_obsolete = false)
    {
        $prices = array();

        foreach ($this->getOrderdetails($hide_obsolete) as $details) {
            $prices[] = $details->getPrice(! $float_array, $quantity, $multiplier);
        }

        if (\is_string($delimeter)) {
            return implode($delimeter, $prices);
        } else {
            return $prices;
        }
    }

    /**
     *  Get the average price of all orderdetails
     *
     * With the $multiplier you're able to multiply the price before it will be returned.
     * This is useful if you want to have the price as a string with currency, but multiplied with a factor.
     *
     * @param boolean   $as_money_string    * if true, the retruned value will be a string incl. currency,
     *                                          ready to print it out. See float_to_money_string().
     *                                      * if false, the returned value is a float
     * @param integer       $quantity       this is the quantity to choose the correct priceinformations
     * @param integer|NULL  $multiplier     * This is the multiplier which will be applied to every single price
     *                                      * If you pass NULL, the number from $quantity will be used
     *
     * @return float        price (if "$as_money_string == false")
     * @return NULL         if there are no prices for this part and "$as_money_string == false"
     * @return string       price with currency (if "$as_money_string == true")
     *
     * @throws Exception    if there was an error
     */
    public function getAveragePrice(bool $as_money_string = false, int $quantity = 1, $multiplier = null)
    {
        $prices = $this->getPrices(true, null, $quantity, $multiplier, true);
        $average_price = null;

        $count = 0;
        foreach ($prices as $price) {
            if ($price !== null) {
                $average_price += $price;
                $count++;
            }
        }

        if ($count > 0) {
            $average_price /= $count;
        }

        if ($as_money_string) {
            return floatToMoneyString($average_price);
        } else {
            return $average_price;
        }
    }

    /**
     *  Get the filename of the master picture (absolute path from filesystem root)
     *
     * @param boolean $use_footprint_filename   * if true, and this part has no picture, this method
     *                                              will return the filename of its footprint (if available)
     *                                          * if false, and this part has no picture,
     *                                              this method will return NULL
     *
     * @return string   the whole path + filename from filesystem root as a UNIX path (with slashes)
     * @return NULL     if there is no picture
     *
     * @throws \Exception if there was an error
     */
    public function getMasterPictureFilename(bool $use_footprint_filename = false) : ?string
    {
        $master_picture = $this->getMasterPictureAttachement(); // returns an Attachement-object

        if ($master_picture !== null) {
            return $master_picture->getFilename();
        }

        if ($use_footprint_filename) {
            $footprint = $this->getFootprint();
            if ($footprint !== null) {
                return $footprint->getFilename();
            }
        }

        return null;
    }

    /**
     * Parses the selected fields and extract Properties of the part.
     * @param bool $use_description Use the description field for parsing
     * @param bool $use_comment Use the comment field for parsing
     * @param bool $use_name Use the name field for parsing
     * @param bool $force_output Properties are parsed even if properties are disabled.
     * @return array A array of PartProperty objects.
     * @return array If Properties are disabled or nothing was detected, then an empty array is returned.
     * @throws Exception
     */
    public function getProperties(bool $use_description = true, bool $use_comment = true, bool $use_name = true, bool $force_output = false) : array
    {
        //TODO
        throw new \Exception("Not implemented yet!");
        /*
        global $config;

        if ($config['properties']['active'] || $force_output) {
            if ($this->getCategory()->getDisableProperties(true)) {
                return array();
            }

            $name = array();
            $desc = array();
            $comm = array();

            if ($use_name === true) {
                $name = $this->getCategory()->getPartnameRegexObj()->getProperties($this->getName());
            }
            if ($use_description === true) {
                $desc = PartProperty::parseDescription($this->getDescription());
            }
            if ($use_comment === true) {
                $comm = PartProperty::parseDescription($this->getComment(false));
            }

            return array_merge($name, $desc, $comm);
        } else {
            return array();
        }*/
    }

    /**
     * Returns a loop (array) of the array representations of the properties of this part.
     * @param bool $use_description Use the description field for parsing
     * @param bool $use_comment Use the comment field for parsing
     * @return array A array of arrays with the name and value of the properties.
     */
    public function getPropertiesLoop(bool $use_description = true, bool $use_comment = true, bool $use_name = true) : array
    {
        //TODO
        throw new \Exception("Not implemented yet!");
        $arr = array();
        foreach ($this->getProperties($use_description, $use_comment, $use_name) as $property) {
            /* @var PartProperty $property */
            $arr[] = $property->getArray(true);
        }
        return $arr;
    }

    /*
    public function hasValidName() : bool
    {
        return self::isValidName($this->getName(), $this->getCategory());
    } */


    public function getAttachmentTypes() : array
    {
        return parent::getAttachmentTypes();
    }

    public function getAttachments($type_id = null, bool $only_table_attachements = false) : array
    {
        return parent::getAttachments($type_id, $only_table_attachements);
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     *  Set the description
     *
     * @param string $new_description       the new description
     *
     * @return self
     */
    public function setDescription(?string $new_description) : self
    {
        $this->description = $new_description;
        return $this;
    }

    /**
     *  Set the count of parts which are in stock
     *
     * @param integer $new_instock       the new count of parts which are in stock
     *
     * @return self
     */
    public function setInstock(int $new_instock, $comment = null) : self
    {
        //Assert::natural($new_instock, 'New instock must be positive. Got: %s');

        $old_instock = (int) $this->getInstock();
        $this->instock = $new_instock;
        //TODO
        /*
        InstockChangedEntry::add(
            $this->database,
            $this->current_user,
            $this->log,
            $this,
            $old_instock,
            $new_instock,
            $comment
        );*/

        return $this;
    }

    /**
     * Sets the unknown status of this part.
     * When the instock is currently unknown and you pass false, then the instock is set to zero.
     * If the instock is not unknown and you pass false, nothing is changed.
     *
     * @param bool $new_unknown Set this to true if the instock should be marked as unknown.
     * @return Part
     */
    public function setInstockUnknown(bool $new_unknown) : self
    {
        if($new_unknown == true) {
            $this->instock = self::INSTOCK_UNKNOWN;
        } else {
            //Change only if instock is currently unknown.
            if ($this->isInstockUnknown()) {
                $this->setInstock(0);
            }
        }

    }

    /**
     * Withdrawal the given number of parts.
     * @param $count int The number of parts which should be withdrawan.
     * @param $comment string A comment that should be associated with the withdrawal.
     *
     * @return self
     */
    public function withdrawalParts(int $count, $comment = null) : self
    {
        //Assert::greaterThan($count,0, 'Count of withdrawn parts must be greater 0! Got %s!');
        //Assert::greaterThan($count, $this->instock, 'You can not withdraw more parts, than there are existing!');

        $old_instock = $this->getInstock();
        $new_instock = $old_instock - $count;

        //TODO
        /*
        InstockChangedEntry::add(
            $this->database,
            $this->current_user,
            $this->log,
            $this,
            $old_instock,
            $new_instock,
            $comment
        );*/

        $this->instock = $new_instock;

        return $this;
    }

    /**
     * Add the given number of parts.
     * @param $count int The number of parts which should be withdrawan.
     * @param $comment string A comment that should be associated with the withdrawal.
     *
     * @return self
     */
    public function addParts(int $count, string $comment = null) : self
    {
        //Assert::greaterThan($count, 0, 'Count of added parts must be greater zero! Got %s.');

        //TODO

        $old_instock = $this->getInstock();
        $new_instock = $old_instock + $count;

        //TODO
        /*
        InstockChangedEntry::add(
            $this->database,
            $this->current_user,
            $this->log,
            $this,
            $old_instock,
            $new_instock,
            $comment
        );*/

        $this->instock = $new_instock;

        return $this;
    }

    /**
     *  Set the count of parts which should be in stock at least
     *
     * @param integer $new_mininstock       the new count of parts which should be in stock at least
     * @return self
     */
    public function setMinInstock(int $new_mininstock) : self
    {
        //Assert::natural($new_mininstock, 'The new minimum instock value must be positive! Got %s.');

        $this->mininstock = $new_mininstock;
        return $this;
    }

    /**
     *  Set the comment
     *
     * @param string $new_comment       the new comment
     *
     * @return self
     */
    public function setComment(string $new_comment) : self
    {
        $this->comment = $new_comment;

        return $this;
    }

    /**
     *  Set the "manual_order" attribute
     *
     * @param boolean $new_manual_order                 the new "manual_order" attribute
     * @param integer $new_order_quantity               the new order quantity
     * @param integer|NULL $new_order_orderdetails_id   * the ID of the new order orderdetails
     *                                                  * or Zero for "no order orderdetails"
     *                                                  * or NULL for automatic order orderdetails
     *                                                      (if the part has exactly one orderdetails,
     *                                                      set this orderdetails as order orderdetails.
     *                                                      Otherwise, set "no order orderdetails")
     *
     * @return self
     */
    public function setManualOrder(bool $new_manual_order, int $new_order_quantity = 1, $new_order_orderdetails_id = null) : self
    {
        //Assert::greaterThan($new_order_quantity, 0, 'The new order quantity must be greater zero. Got %s!');


        $this->manual_order = $new_manual_order;

        //TODO;
        /* $this->order_orderdetail = $new_order_orderdetails_id; */
        $this->order_quantity = $new_order_quantity;

        return $this;
    }

    /**
     *  Set the ID of the order orderdetails
     *
     * @param integer|NULL $new_order_orderdetails_id       * the new order orderdetails ID
     *                                                      * Or, to remove the orderdetails, pass a NULL
     *
     * @return self
     */
    public function setOrderOrderdetailsID($new_order_orderdetails_id) : self
    {
        //TODO
        throw new \Exception("Not implemented yet...");

        return $this;
    }

    /**
     *  Set the order quantity
     *
     * @param integer $new_order_quantity       the new order quantity
     *
     * @return self
     */
    public function setOrderQuantity(int $new_order_quantity) : self
    {
        //Assert::greaterThan($new_order_quantity,0, 'The new order quantity must be greater zero. Got %s!');

        $this->order_quantity = $new_order_quantity;

        return $this;
    }

    /**
     *  Set the category of this Part
     *
     *     Every part must have a valid category (in contrast to the
     *          attributes "footprint", "storelocation", ...)!
     *
     * @param  Category $category       The new category of this part
     *
     * @return self
     */
    public function setCategory(Category $category) : self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Set the new Footprint of this Part.
     *
     * @param Footprint|null $new_footprint The new footprint of this part. Set to null, if this part should not have
     * a footprint.
     * @return self
     */
    public function setFootprint(?Footprint $new_footprint) : self
    {
        $this->footprint = $new_footprint;

        return $this;
    }


    /**
     * Set the new store location of this part.
     *
     * @param Storelocation|null $new_storelocation The new Storelocation of this part. Set to null, if this part should
     * not have a storelocation.
     * @return Part
     */
    public function setStorelocation(?Storelocation $new_storelocation) : self
    {
        $this->storelocation = $new_storelocation;

        return $this;
    }

    /**
     * Sets the new manufacturer of this part.
     *
     * @param Manufacturer|null $new_manufacturer The new Manufacturer of this part. Set to null, if this part should
     * not have a manufacturer.
     * @return Part
     */
    public function setManufacturer(?Manufacturer $new_manufacturer) : self
    {
        $this->manufacturer = $new_manufacturer;

        return $this;
    }

    /**
     * Set the favorite status for this part.
     * @param $new_favorite_status bool The new favorite status, that should be applied on this part.
     *      Set this to true, when the part should be a favorite.
     *
     * @return self
     */
    public function setFavorite(bool $new_favorite_status) : self
    {
        $this->favorite = $new_favorite_status;

        return $this;
    }

    /**
     * Sets the URL to the manufacturer site about this Part. Set to "" if this part should use the automatically URL based on its manufacturer.
     * @param string $new_url The new url
     * @return self
     */
    public function setManufacturerProductURL(string $new_url) : self
    {
        $this->manufacturer_product_url = $new_url;

        return $this;
    }

    /**
     *  Set the ID of the master picture Attachement
     *
     * @param integer|NULL $new_master_picture_attachement_id       * the ID of the Attachement object of the master picture
     *                                                              * NULL means "no master picture"
     *
     * @throws Exception if the new ID is not valid
     * @throws Exception if there was an error
     *
     * @return self
     */
    public function setMasterPictureAttachementID($new_master_picture_attachement_id) : self
    {
        //TODO
        throw new \Exception("Not implemented yet!");

        return $this;
    }


}