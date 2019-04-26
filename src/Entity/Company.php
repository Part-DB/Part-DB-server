<?php

declare(strict_types=1);
/**
 * Part-DB Version 0.4+ "nextgen"
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics.
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

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This abstract class is used for companies like suppliers or manufacturers.
 *
 * @ORM\MappedSuperclass()
 */
abstract class Company extends StructuralDBElement
{
    /**
     * @var string The address of the company
     * @ORM\Column(type="string")
     */
    protected $address = "";

    /**
     * @var string The phone number of the company
     * @ORM\Column(type="string")
     */
    protected $phone_number = "";

    /**
     * @var string The fax number of the company
     * @ORM\Column(type="string")
     */
    protected $fax_number = "";

    /**
     * @var string The email address of the company
     * @ORM\Column(type="string")
     * @Assert\Email()
     */
    protected $email_address = "";

    /**
     * @var string The website of the company
     * @ORM\Column(type="string")
     * @Assert\Url()
     */
    protected $website = "";

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $auto_product_url = "";

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Get the address.
     *
     * @return string the address of the company (with "\n" as line break)
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * Get the phone number.
     *
     * @return string the phone number of the company
     */
    public function getPhoneNumber(): string
    {
        return $this->phone_number;
    }

    /**
     * Get the fax number.
     *
     * @return string the fax number of the company
     */
    public function getFaxNumber(): string
    {
        return $this->fax_number;
    }

    /**
     * Get the e-mail address.
     *
     * @return string the e-mail address of the company
     */
    public function getEmailAddress(): string
    {
        return $this->email_address;
    }

    /**
     * Get the website.
     *
     * @return string the website of the company
     */
    public function getWebsite(): string
    {
        return $this->website;
    }

    /**
     * Get the link to the website of an article.
     *
     * @param string $partnr * NULL for returning the URL with a placeholder for the part number
     *                       * or the part number for returning the direct URL to the article
     *
     * @return string the link to the article
     */
    public function getAutoProductUrl($partnr = null): string
    {
        if (\is_string($partnr)) {
            return str_replace('%PARTNUMBER%', $partnr, $this->auto_product_url);
        }

        return $this->auto_product_url;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     * Set the addres.
     *
     * @param string $new_address the new address (with "\n" as line break)
     *
     * @return self
     */
    public function setAddress(string $new_address): self
    {
        $this->address = $new_address;

        return $this;
    }

    /**
     * Set the phone number.
     *
     * @param string $new_phone_number the new phone number
     *
     * @return self
     */
    public function setPhoneNumber(string $new_phone_number): self
    {
        $this->phone_number = $new_phone_number;

        return $this;
    }

    /**
     * Set the fax number.
     *
     * @param string $new_fax_number the new fax number
     *
     * @return self
     */
    public function setFaxNumber(string $new_fax_number): self
    {
        $this->fax_number = $new_fax_number;

        return $this;
    }

    /**
     * Set the e-mail address.
     *
     * @param string $new_email_address the new e-mail address
     *
     * @return self
     */
    public function setEmailAddress(string $new_email_address): self
    {
        $this->email_address = $new_email_address;

        return $this;
    }

    /**
     * Set the website.
     *
     * @param string $new_website the new website
     *
     * @return self
     */
    public function setWebsite(string $new_website): self
    {
        $this->website = $new_website;

        return $this;
    }

    /**
     * Set the link to the website of an article.
     *
     * @param string $new_url the new URL with the placeholder %PARTNUMBER% for the part number
     *
     * @return self
     */
    public function setAutoProductUrl(string $new_url): self
    {
        $this->auto_product_url = $new_url;

        return $this;
    }
}
