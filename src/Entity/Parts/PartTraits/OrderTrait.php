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
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Entity\Parts\PartTraits;

use App\Entity\PriceInformations\Orderdetail;
use App\Security\Annotations\ColumnSecurity;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use function count;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * This trait collects all aspects of a part related to orders and priceinformations.
 */
trait OrderTrait
{
    /**
     * @var Orderdetail[]|Collection the details about how and where you can order this part
     * @ORM\OneToMany(targetEntity="App\Entity\PriceInformations\Orderdetail", mappedBy="part", cascade={"persist", "remove"}, orphanRemoval=true)
     * @Assert\Valid()
     * @ColumnSecurity(prefix="orderdetails", type="collection")
     * @ORM\OrderBy({"supplierpartnr" = "ASC"})
     */
    protected $orderdetails;

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
     * @var Orderdetail
     * @ORM\OneToOne(targetEntity="App\Entity\PriceInformations\Orderdetail")
     * @ORM\JoinColumn(name="order_orderdetails_id", referencedColumnName="id")
     *
     * @ColumnSecurity(prefix="order", type="object")
     */
    protected $order_orderdetail;

    /**
     * Get the selected order orderdetails of this part.
     *
     * @return Orderdetail the selected order orderdetails
     */
    public function getOrderOrderdetails(): ?Orderdetail
    {
        return $this->order_orderdetail;
    }

    /**
     * Get the order quantity of this part.
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
     *  Get all orderdetails of this part.
     *
     * @param bool $hide_obsolete If true, obsolete orderdetails will NOT be returned
     *
     * @return Collection|Orderdetail[] * all orderdetails as a one-dimensional array of Orderdetails objects
     *                                  (empty array if there are no ones)
     *                                  * the array is sorted by the suppliers names / minimum order quantity
     */
    public function getOrderdetails(bool $hide_obsolete = false): Collection
    {
        //If needed hide the obsolete entries
        if ($hide_obsolete) {
            return $this->orderdetails->filter(function (Orderdetail $orderdetail) {
                return ! $orderdetail->getObsolete();
            });
        }

        return $this->orderdetails;
    }

    /**
     * Adds the given orderdetail to list of orderdetails.
     * The orderdetail is assigned to this part.
     *
     * @param Orderdetail $orderdetail the orderdetail that should be added
     *
     * @return $this
     */
    public function addOrderdetail(Orderdetail $orderdetail): self
    {
        $orderdetail->setPart($this);
        $this->orderdetails->add($orderdetail);

        return $this;
    }

    /**
     * Removes the given orderdetail from the list of orderdetails.
     *
     * @return $this
     */
    public function removeOrderdetail(Orderdetail $orderdetail): self
    {
        $this->orderdetails->removeElement($orderdetail);

        return $this;
    }

    /**
     *  Set the "manual_order" attribute.
     *
     * @param bool             $new_manual_order      the new "manual_order" attribute
     * @param int              $new_order_quantity    the new order quantity
     * @param Orderdetail|null $new_order_orderdetail * the ID of the new order orderdetails
     *                                                * or Zero for "no order orderdetails"
     *                                                * or NULL for automatic order orderdetails
     *                                                (if the part has exactly one orderdetails,
     *                                                set this orderdetails as order orderdetails.
     *                                                Otherwise, set "no order orderdetails")
     *
     * @return $this
     */
    public function setManualOrder(bool $new_manual_order, int $new_order_quantity = 1, ?Orderdetail $new_order_orderdetail = null): self
    {
        //Assert::greaterThan($new_order_quantity, 0, 'The new order quantity must be greater zero. Got %s!');

        $this->manual_order = $new_manual_order;

        //TODO;
        $this->order_orderdetail = $new_order_orderdetail;
        $this->order_quantity = $new_order_quantity;

        return $this;
    }

    /**
     * Check if this part is obsolete.
     *
     * A Part is marked as "obsolete" if all their orderdetails are marked as "obsolete".
     * If a part has no orderdetails, the part isn't marked as obsolete.
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
}
