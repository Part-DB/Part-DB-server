<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Entity\Base;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use function is_string;

/**
 * Trait for company-specific fields like address, phone, email, etc.
 */
trait CompanyTrait
{
    /**
     * @var string The address of the company
     */
    #[Groups(['full', 'company:read', 'company:write', 'import', 'extended'])]
    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 255)]
    protected string $address = '';

    /**
     * @var string The phone number of the company
     */
    #[Groups(['full', 'company:read', 'company:write', 'import', 'extended'])]
    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 255)]
    protected string $phone_number = '';

    /**
     * @var string The fax number of the company
     */
    #[Groups(['full', 'company:read', 'company:write', 'import', 'extended'])]
    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 255)]
    protected string $fax_number = '';

    /**
     * @var string The email address of the company
     */
    #[Assert\Email]
    #[Groups(['full', 'company:read', 'company:write', 'import', 'extended'])]
    #[ORM\Column(type: Types::STRING)]
    #[Assert\Length(max: 255)]
    protected string $email_address = '';

    /**
     * @var string The website of the company
     */
    #[Assert\Url(requireTld: false)]
    #[Groups(['full', 'company:read', 'company:write', 'import', 'extended'])]
    #[ORM\Column(type: Types::STRING, length: 2048)]
    #[Assert\Length(max: 2048)]
    protected string $website = '';

    /**
     * @var string The link to the website of an article. Use %PARTNUMBER% as placeholder for the part number.
     */
    #[ORM\Column(type: Types::STRING, length: 2048)]
    #[Assert\Length(max: 2048)]
    #[Groups(['full', 'company:read', 'company:write', 'import', 'extended'])]
    protected string $auto_product_url = '';

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
     * Set the address.
     *
     * @param string $new_address the new address (with "\n" as line break)
     *
     * @return $this
     */
    public function setAddress(string $new_address): self
    {
        $this->address = $new_address;

        return $this;
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
     * Set the phone number.
     *
     * @param string $new_phone_number the new phone number
     *
     * @return $this
     */
    public function setPhoneNumber(string $new_phone_number): self
    {
        $this->phone_number = $new_phone_number;

        return $this;
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
     * Set the fax number.
     *
     * @param string $new_fax_number the new fax number
     *
     * @return $this
     */
    public function setFaxNumber(string $new_fax_number): self
    {
        $this->fax_number = $new_fax_number;

        return $this;
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
     * Set the e-mail address.
     *
     * @param string $new_email_address the new e-mail address
     *
     * @return $this
     */
    public function setEmailAddress(string $new_email_address): self
    {
        $this->email_address = $new_email_address;

        return $this;
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
     * Set the website.
     *
     * @param string $new_website the new website
     *
     * @return $this
     */
    public function setWebsite(string $new_website): self
    {
        $this->website = $new_website;

        return $this;
    }

    /**
     * Get the link to the website of an article.
     *
     * @param  string|null  $partnr * NULL for returning the URL with a placeholder for the part number
     *                       * or the part number for returning the direct URL to the article
     *
     * @return string the link to the article
     */
    public function getAutoProductUrl(?string $partnr = null): string
    {
        if (is_string($partnr)) {
            return str_replace('%PARTNUMBER%', $partnr, $this->auto_product_url);
        }

        return $this->auto_product_url;
    }

    /**
     * Set the link to the website of an article.
     *
     * @param string $new_url the new URL with the placeholder %PARTNUMBER% for the part number
     *
     * @return $this
     */
    public function setAutoProductUrl(string $new_url): self
    {
        $this->auto_product_url = $new_url;

        return $this;
    }
}
