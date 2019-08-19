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

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Devices\Device;
use App\Entity\PriceInformations\Orderdetail;
use App\Security\Annotations\ColumnSecurity;
use App\Validator\Constraints\Selectable;
use Doctrine\ORM\Mapping as ORM;

use Doctrine\ORM\PersistentCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Part.
 *
 * @ORM\Entity(repositoryClass="App\Repository\PartRepository")
 * @ORM\Table("`parts`")
 */
class Part extends AttachmentContainingDBElement
{
    public const INSTOCK_UNKNOWN = -2;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\PartAttachment", mappedBy="element")
     */
    protected $attachments;

    /**
     * @var Category
     * @ORM\ManyToOne(targetEntity="Category", inversedBy="parts")
     * @ORM\JoinColumn(name="id_category", referencedColumnName="id")
     * @Selectable()
     */
    protected $category;

    /**
     * @var Footprint|null
     * @ORM\ManyToOne(targetEntity="Footprint", inversedBy="parts")
     * @ORM\JoinColumn(name="id_footprint", referencedColumnName="id")
     *
     * @ColumnSecurity(prefix="footprint", type="object")
     * @Selectable()
     */
    protected $footprint;

    /**
     * @var Manufacturer|null
     * @ORM\ManyToOne(targetEntity="Manufacturer", inversedBy="parts")
     * @ORM\JoinColumn(name="id_manufacturer", referencedColumnName="id")
     *
     * @ColumnSecurity(prefix="manufacturer", type="object")
     * @Selectable()
     */
    protected $manufacturer;

    /**
     * @var Attachment
     * @ORM\ManyToOne(targetEntity="App\Entity\Attachments\Attachment")
     * @ORM\JoinColumn(name="id_master_picture_attachement", referencedColumnName="id")
     *
     * @ColumnSecurity(prefix="attachments", type="object")
     */
    protected $master_picture_attachment;

    /**
     * @var Orderdetail[]
     * @ORM\OneToMany(targetEntity="App\Entity\PriceInformations\Orderdetail", mappedBy="part")
     *
     * @ColumnSecurity(prefix="orderdetails", type="object")
     */
    protected $orderdetails;

    /**
     * @var Orderdetail
     * @ORM\OneToOne(targetEntity="App\Entity\PriceInformations\Orderdetail")
     * @ORM\JoinColumn(name="order_orderdetails_id", referencedColumnName="id")
     *
     * @ColumnSecurity(prefix="order", type="object")
     */
    protected $order_orderdetail;

    //TODO
    protected $devices;

    /**
     *  @ColumnSecurity(type="datetime")
     *  @ORM\Column(type="datetimetz", name="datetime_added")
     */
    protected $addedDate;

    /**
     * @var \DateTime The date when this element was modified the last time.
     * @ORM\Column(type="datetimetz", name="last_modified")
     * @ColumnSecurity(type="datetime")
     */
    protected $lastModified;

    /**********************
     * Propertys
     ***********************/

    /**
     * @var string
     * @ORM\Column(type="string")
     *
     * @ColumnSecurity(prefix="name")
     */
    protected $name;

    /**
     * @var string
     * @ORM\Column(type="text")
     *
     * @ColumnSecurity(prefix="description")
     */
    protected $description = '';

    /**
     * @var ?PartLot[]
     * @ORM\OneToMany(targetEntity="PartLot", mappedBy="part", cascade={"persist", "remove"}, orphanRemoval=true)
     * @Assert\Valid()
     */
    protected $partLots;

    /**
     * @var float
     * @ORM\Column(type="float")
     * @Assert\PositiveOrZero()
     *
     * @ColumnSecurity(prefix="mininstock", type="integer")
     */
    protected $minamount = 0;

    /**
     * @var string
     * @ORM\Column(type="text")
     * @ColumnSecurity(prefix="comment")
     */
    protected $comment = '';

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $visible = true;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     * @ColumnSecurity(type="boolean")
     */
    protected $favorite = false;

    /**
     * @var int
     * @ORM\Column(type="integer")
     * @ColumnSecurity(prefix="order", type="integer")
     */
    protected $order_quantity = 0;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     * @ColumnSecurity(prefix="order", type="boolean")
     */
    protected $manual_order = false;

    /**
     * @var string
     * @ORM\Column(type="string")
     * @ColumnSecurity(prefix="manufacturer", type="string", placeholder="")
     */
    protected $manufacturer_product_url = '';

    /**
     * @var string
     * @ORM\Column(type="string")
     * @ColumnSecurity(prefix="manufacturer", type="string", placeholder="")
     */
    protected $manufacturer_product_number;

    /**
     * @var bool Determines if this part entry needs review (for example, because it is work in progress)
     * @ORM\Column(type="boolean")
     */
    protected $needs_review = false;

    /**
     * @var ?MeasurementUnit The unit in which the part's amount is measured.
     * @ORM\ManyToOne(targetEntity="MeasurementUnit")
     * @ORM\JoinColumn(name="id_part_unit", referencedColumnName="id", nullable=true)
     */
    protected $partUnit;

    /**
     * @var string A comma seperated list of tags, assocciated with the part.
     * @ORM\Column(type="text")
     */
    protected $tags;

    /**
     * @var float|null How much a single part unit weighs in gramms.
     * @ORM\Column(type="float", nullable=true)
     * @Assert\Positive()
     */
    protected $mass;

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
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
     * @return string the description
     */
    public function getDescription(): string
    {
        return  htmlspecialchars($this->description);
    }

    /**
     *  Get the count of parts which must be in stock at least.
     *
     * @return int count of parts which must be in stock at least
     */
    public function getMinAmount(): float
    {
        return $this->minamount;
    }

    /**
     *  Get the comment associated with this part.
     *
     * @return string The raw/unparsed comment
     */
    public function getComment(): string
    {
        return htmlspecialchars($this->comment);
    }

    /**
     *  Get if this part is obsolete.
     *
     *     A Part is marked as "obsolete" if all their orderdetails are marked as "obsolete".
     *          If a part has no orderdetails, the part isn't marked as obsolete.
     *
     * @return bool true, if this part is obsolete. false, if this part isn't obsolete
     */
    public function isObsolete(): bool
    {
        $all_orderdetails = $this->getOrderdetails();

        if (0 === count($all_orderdetails)) {
            return false;
        }

        foreach ($all_orderdetails as $orderdetails) {
            if (!$orderdetails->getObsolete()) {
                return false;
            }
        }

        return true;
    }

    /**
     *  Get if this part is visible.
     *
     * @return bool true if this part is visible
     *              false if this part isn't visible
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * Get if this part is a favorite.
     *
     * @return bool * true if this part is a favorite
     *              * false if this part is not a favorite.
     */
    public function isFavorite(): bool
    {
        return $this->favorite;
    }

    /**
     *  Get the selected order orderdetails of this part.
     * @return Orderdetail the selected order orderdetails
     */
    public function getOrderOrderdetails(): ?Orderdetail
    {
        return $this->order_orderdetail;
    }

    /**
     *  Get the order quantity of this part.
     *
     * @return int the order quantity
     */
    public function getOrderQuantity(): int
    {
        return $this->order_quantity;
    }

    /**
     *  Check if this part is marked for manual ordering.
     *
     * @return bool the "manual_order" attribute
     */
    public function isMarkedForManualOrder(): bool
    {
        return $this->manual_order;
    }

    /**
     *  Get the link to the website of the article on the manufacturers website
     *  When no this part has no explicit url set, then it is tried to generate one from the Manufacturer of this part
     *  automatically.
     *
     * @param
     *
     * @return string the link to the article
     */
    public function getManufacturerProductUrl(): string
    {
        if ('' !== $this->manufacturer_product_url) {
            return $this->manufacturer_product_url;
        }

        if (null !== $this->getManufacturer()) {
            return $this->getManufacturer()->getAutoProductUrl($this->name);
        }

        return ''; // no url is available
    }

    /**
     * Similar to getManufacturerProductUrl, but here only the database value is returned.
     *
     * @return string The manufacturer url saved in DB for this part.
     */
    public function getCustomProductURL(): string
    {
        return $this->manufacturer_product_url;
    }

    /**
     *  Get the category of this part.
     *
     * There is always a category, for each part!
     *
     * @return Category the category of this part
     */
    public function getCategory(): Category
    {
        return $this->category;
    }

    /**
     *  Get the footprint of this part (if there is one).
     *
     * @return Footprint the footprint of this part (if there is one)
     */
    public function getFootprint(): ?Footprint
    {
        return $this->footprint;
    }

    /**
     *  Get the manufacturer of this part (if there is one).
     *
     * @return Manufacturer the manufacturer of this part (if there is one)
     */
    public function getManufacturer(): ?Manufacturer
    {
        return $this->manufacturer;
    }

    /**
     *  Get the master picture "Attachement"-object of this part (if there is one).
     *
     * @return Attachment the master picture Attachement of this part (if there is one)
     */
    public function getMasterPictureAttachement(): ?Attachment
    {
        return $this->master_picture_attachment;
    }

    /**
     *  Get all orderdetails of this part.
     *
     * @param bool $hide_obsolete If true, obsolete orderdetails will NOT be returned
     *
     * @return Orderdetail[] * all orderdetails as a one-dimensional array of Orderdetails objects
     *                        (empty array if there are no ones)
     *                        * the array is sorted by the suppliers names / minimum order quantity
     *
     */
    public function getOrderdetails(bool $hide_obsolete = false) : PersistentCollection
    {
        //If needed hide the obsolete entries
        if ($hide_obsolete) {
            $orderdetails = $this->orderdetails;
            foreach ($orderdetails as $key => $details) {
                if ($details->getObsolete()) {
                    unset($orderdetails[$key]);
                }
            }

            return $orderdetails;
        }

        return $this->orderdetails;
    }

    /**
     *  Get all devices which uses this part.
     *
     * @return Device[] * all devices which uses this part as a one-dimensional array of Device objects
     *                  (empty array if there are no ones)
     *                  * the array is sorted by the devices names
     *
     */
    public function getDevices(): array
    {
        return $this->devices;
    }

    /**
     *  Get all prices of this part.
     *
     * This method simply gets the prices of the orderdetails and prepare them.\n
     * In the returned array/string there is a price for every supplier.
     * @param int $quantity this is the quantity to choose the correct priceinformation
     * @param int|null $multiplier * This is the multiplier which will be applied to every single price
     *                                   * If you pass NULL, the number from $quantity will be used
     * @param bool $hide_obsolete If true, prices from obsolete orderdetails will NOT be returned
     *
     * @return float[]  all prices as an array of floats (if "$delimeter == NULL" & "$float_array == true")
     *
     * @throws \Exception if there was an error
     */
    public function getPrices(int $quantity = 1, $multiplier = null, bool $hide_obsolete = false) : array
    {
        $prices = array();

        foreach ($this->getOrderdetails($hide_obsolete) as $details) {
            $prices[] = $details->getPrice($quantity, $multiplier);
        }

        return $prices;
    }

    /**
     *  Get the average price of all orderdetails.
     *
     * With the $multiplier you're able to multiply the price before it will be returned.
     * This is useful if you want to have the price as a string with currency, but multiplied with a factor.
     *
     * @param int      $quantity        this is the quantity to choose the correct priceinformations
     * @param int|null $multiplier      * This is the multiplier which will be applied to every single price
     *                                  * If you pass NULL, the number from $quantity will be used
     *
     * @return float  price (if "$as_money_string == false")
     *
     * @throws \Exception if there was an error
     */
    public function getAveragePrice(int $quantity = 1, $multiplier = null) : ?float
    {
        $prices = $this->getPrices($quantity, $multiplier, true);
        //Findout out

        $average_price = null;

        $count = 0;
        foreach ($this->getOrderdetails() as $orderdetail) {
            $price = $orderdetail->getPrice(1, null);
            if (null !== $price) {
                $average_price += $price;
                ++$count;
            }
        }

        if ($count > 0) {
            $average_price /= $count;
        }

        return $average_price;
    }

    /**
     * Checks if this part is marked, for that it needs further review.
     * @return bool
     */
    public function isNeedsReview(): bool
    {
        return $this->needs_review;
    }

    /**
     * Sets the "needs review" status of this part.
     * @param bool $needs_review
     * @return Part
     */
    public function setNeedsReview(bool $needs_review): Part
    {
        $this->needs_review = $needs_review;
        return $this;
    }

    /**
     * Get all part lots where this part is stored.
     * @return PartLot[]|PersistentCollection
     */
    public function getPartLots() : PersistentCollection
    {
        return $this->partLots;
    }

    public function addPartLot(PartLot $lot): Part
    {
        $lot->setPart($this);
        $this->partLots->add($lot);
        return $this;
    }

    public function removePartLot(PartLot $lot): Part
    {
        $this->partLots->removeElement($lot);
        return $this;
    }

    /**
     * Returns the assigned manufacturer product number (MPN) for this part.
     * @return string
     */
    public function getManufacturerProductNumber(): string
    {
        return $this->manufacturer_product_number;
    }

    /**
     * Sets the manufacturer product number (MPN) for this part.
     * @param string $manufacturer_product_number
     * @return Part
     */
    public function setManufacturerProductNumber(string $manufacturer_product_number): Part
    {
        $this->manufacturer_product_number = $manufacturer_product_number;
        return $this;
    }

    /**
     * Gets the measurement unit in which the part's amount should be measured.
     * Returns null if no specific unit was that. That means the parts are measured simply in quantity numbers.
     * @return ?MeasurementUnit
     */
    public function getPartUnit(): ?MeasurementUnit
    {
        return $this->partUnit;
    }

    /**
     * Sets the measurement unit in which the part's amount should be measured.
     * Set to null, if the part should be measured in quantities.
     * @param ?MeasurementUnit $partUnit
     * @return Part
     */
    public function setPartUnit(?MeasurementUnit $partUnit): Part
    {
        $this->partUnit = $partUnit;
        return $this;
    }

    /**
     * Gets a comma separated list, of tags, that are assigned to this part
     * @return string
     */
    public function getTags(): string
    {
        return $this->tags;
    }

    /**
     * Sets a comma separated list of tags, that are assigned to this part.
     * @param string $tags
     * @return Part
     */
    public function setTags(string $tags): Part
    {
        $this->tags = $tags;
        return $this;
    }

    /**
     * Returns the mass of a single part unit.
     * Returns null, if the mass is unknown/not set yet
     * @return float|null
     */
    public function getMass(): ?float
    {
        return $this->mass;
    }

    /**
     * Sets the mass of a single part unit.
     * Sett to null, if the mass is unknown.
     * @param float|null $mass
     * @return Part
     */
    public function setMass(?float $mass): Part
    {
        $this->mass = $mass;
        return $this;
    }

    /**
     * Checks if this part uses the float amount .
     * This setting is based on the part unit (see MeasurementUnit->isInteger()).
     * @return bool True if the float amount field should be used. False if the integer instock field should be used.
     */
    public function useFloatAmount(): bool
    {
        if ($this->partUnit instanceof MeasurementUnit) {
            return $this->partUnit->isInteger();
        }

        //When no part unit is set, treat it as part count, and so use the integer value.
        return false;
    }

    /**
     * Returns the summed amount of this part (over all part lots)
     * @return float
     */
    public function getAmountSum() : float
    {
        //TODO: Find a method to do this natively in SQL, the current method could be a bit slow
        $sum = 0;
        foreach($this->getPartLots() as $lot) {
            //Dont use the instock value, if it is unkown
            if ($lot->isInstockUnknown()) {
                continue;
            }

            $sum += $lot->getAmount();
        }

        if(!$this->useFloatAmount()) {
            return $sum;
        }

        return round($sum);
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     *  Set the description.
     *
     * @param string $new_description the new description
     *
     * @return self
     */
    public function setDescription(?string $new_description): self
    {
        $this->description = $new_description;

        return $this;
    }

    /**
     *  Set the minimum amount of parts that have to be instock.
     *  See getPartUnit() for the associated unit.
     *
     * @param int $new_mininstock the new count of parts which should be in stock at least
     *
     * @return self
     */
    public function setMinAmount(float $new_minamount): self
    {
        //Assert::natural($new_mininstock, 'The new minimum instock value must be positive! Got %s.');

        $this->minamount = $new_minamount;

        return $this;
    }

    /**
     *  Set the comment.
     *
     * @param string $new_comment the new comment
     *
     * @return self
     */
    public function setComment(string $new_comment): self
    {
        $this->comment = $new_comment;

        return $this;
    }

    /**
     *  Set the "manual_order" attribute.
     *
     * @param bool     $new_manual_order          the new "manual_order" attribute
     * @param int      $new_order_quantity        the new order quantity
     * @param int|null $new_order_orderdetails_id * the ID of the new order orderdetails
     *                                            * or Zero for "no order orderdetails"
     *                                            * or NULL for automatic order orderdetails
     *                                            (if the part has exactly one orderdetails,
     *                                            set this orderdetails as order orderdetails.
     *                                            Otherwise, set "no order orderdetails")
     *
     * @return self
     */
    public function setManualOrder(bool $new_manual_order, int $new_order_quantity = 1, ?Orderdetail $new_order_orderdetail = null): Part
    {
        //Assert::greaterThan($new_order_quantity, 0, 'The new order quantity must be greater zero. Got %s!');

        $this->manual_order = $new_manual_order;

        //TODO;
        $this->order_orderdetail = $new_order_orderdetail;
        $this->order_quantity = $new_order_quantity;

        return $this;
    }

    /**
     *  Set the category of this Part.
     *
     *     Every part must have a valid category (in contrast to the
     *          attributes "footprint", "storelocation", ...)!
     *
     * @param Category $category The new category of this part
     *
     * @return self
     */
    public function setCategory(Category $category): Part
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Set the new Footprint of this Part.
     *
     * @param Footprint|null $new_footprint The new footprint of this part. Set to null, if this part should not have
     *                                      a footprint.
     *
     * @return self
     */
    public function setFootprint(?Footprint $new_footprint): Part
    {
        $this->footprint = $new_footprint;

        return $this;
    }

    /**
     * Sets the new manufacturer of this part.
     *
     * @param Manufacturer|null $new_manufacturer The new Manufacturer of this part. Set to null, if this part should
     *                                            not have a manufacturer.
     *
     * @return Part
     */
    public function setManufacturer(?Manufacturer $new_manufacturer): self
    {
        $this->manufacturer = $new_manufacturer;

        return $this;
    }

    /**
     * Set the favorite status for this part.
     *
     * @param $new_favorite_status bool The new favorite status, that should be applied on this part.
     *      Set this to true, when the part should be a favorite.
     *
     * @return self
     */
    public function setFavorite(bool $new_favorite_status): self
    {
        $this->favorite = $new_favorite_status;

        return $this;
    }

    /**
     * Sets the URL to the manufacturer site about this Part. Set to "" if this part should use the automatically URL based on its manufacturer.
     *
     * @param string $new_url The new url
     *
     * @return self
     */
    public function setManufacturerProductURL(string $new_url): self
    {
        $this->manufacturer_product_url = $new_url;

        return $this;
    }
}
