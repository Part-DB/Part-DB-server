<?php

declare(strict_types=1);

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

namespace App\Services\LabelSystem;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Base\AbstractCompany;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\Contracts\TimeStampableInterface;
use App\Entity\LabelSystem\LabelOptions;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\UserSystem\User;
use App\Twig\FormatExtension;
use App\Twig\Sandbox\InheritanceSecurityPolicy;
use InvalidArgumentException;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityPolicyInterface;

final class SandboxedTwigProvider
{
    private const ALLOWED_TAGS = ['apply', 'autoescape', 'do', 'for', 'if', 'set', 'verbatim', 'with'];
    private const ALLOWED_FILTERS = ['abs', 'batch', 'capitalize', 'column', 'country_name',
        'currency_name', 'currency_symbol', 'date', 'date_modify', 'default', 'escape', 'filter', 'first', 'format',
        'format_currency', 'format_date', 'format_datetime', 'format_number', 'format_time', 'join', 'keys',
        'language_name', 'last', 'length', 'locale_name', 'lower', 'map', 'merge', 'nl2br', 'raw', 'number_format',
        'reduce', 'replace', 'reverse', 'slice', 'sort', 'spaceless', 'split', 'striptags', 'timezone_name', 'title',
        'trim', 'upper', 'url_encode',
        //Part-DB specific filters:
        'moneyFormat', 'siFormat', 'amountFormat', ];

    private const ALLOWED_FUNCTIONS = ['date', 'html_classes', 'max', 'min', 'random', 'range'];

    private const ALLOWED_METHODS = [
        NamedElementInterface::class => ['getName'],
        AbstractDBElement::class => ['getID', '__toString'],
        TimeStampableInterface::class => ['getLastModified', 'getAddedDate'],
        AbstractStructuralDBElement::class => ['isChildOf', 'isRoot', 'getParent', 'getComment', 'getLevel',
            'getFullPath', 'getPathArray', 'getChildren', 'isNotSelectable', ],
        AbstractCompany::class => ['getAddress', 'getPhoneNumber', 'getFaxNumber', 'getEmailAddress', 'getWebsite'],
        AttachmentContainingDBElement::class => ['getAttachments', 'getMasterPictureAttachment'],
        Attachment::class => ['isPicture', 'is3DModel', 'isExternal', 'isSecure', 'isBuiltIn', 'getExtension',
            'getElement', 'getURL', 'getFilename', 'getAttachmentType', 'getShowInTable', ],
        AbstractParameter::class => ['getFormattedValue', 'getGroup', 'getSymbol', 'getValueMin', 'getValueMax',
            'getValueTypical', 'getUnit', 'getValueText', ],
        MeasurementUnit::class => ['getUnit', 'isInteger', 'useSIPrefix'],
        PartLot::class => ['isExpired', 'getDescription', 'getComment', 'getExpirationDate', 'getStorageLocation',
            'getPart', 'isInstockUnknown', 'getAmount', 'getNeedsRefill', ],
        Storelocation::class => ['isFull', 'isOnlySinglePart', 'isLimitToExistingParts', 'getStorageType'],
        Supplier::class => ['getShippingCosts', 'getDefaultCurrency'],
        Part::class => ['isNeedsReview', 'getTags', 'getMass', 'getDescription', 'isFavorite', 'getCategory',
            'getFootprint', 'getPartLots', 'getPartUnit', 'useFloatAmount', 'getMinAmount', 'getAmountSum',
            'getManufacturerProductUrl', 'getCustomProductURL', 'getManufacturingStatus', 'getManufacturer',
            'getManufacturerProductNumber', 'getOrderdetails', 'isObsolete', ],
        Currency::class => ['getIsoCode', 'getInverseExchangeRate', 'getExchangeRate'],
        Orderdetail::class => ['getPart', 'getSupplier', 'getSupplierPartNr', 'getObsolete',
            'getPricedetails', 'findPriceForQty', ],
        Pricedetail::class => ['getOrderdetail', 'getPrice', 'getPricePerUnit', 'getPriceRelatedQuantity',
            'getMinDiscountQuantity', 'getCurrency', ],
        //Only allow very little information about users...
        User::class => ['isAnonymousUser', 'getUsername', 'getFullName', 'getFirstName', 'getLastName',
            'getDepartment', 'getEmail', ],
    ];
    private const ALLOWED_PROPERTIES = [];

    private FormatExtension $appExtension;

    public function __construct(FormatExtension $appExtension)
    {
        $this->appExtension = $appExtension;
    }

    public function getTwig(LabelOptions $options): Environment
    {
        if ('twig' !== $options->getLinesMode()) {
            throw new InvalidArgumentException('The LabelOptions must explicitly allow twig via lines_mode = "twig"!');
        }

        $loader = new ArrayLoader([
            'lines' => $options->getLines(),
        ]);
        $twig = new Environment($loader);

        //Second argument activate sandbox globally.
        $sandbox = new SandboxExtension($this->getSecurityPolicy(), true);
        $twig->addExtension($sandbox);

        //Add IntlExtension
        $twig->addExtension(new IntlExtension());

        //Add Part-DB specific extension
        $twig->addExtension($this->appExtension);

        return $twig;
    }

    private function getSecurityPolicy(): SecurityPolicyInterface
    {
        return new InheritanceSecurityPolicy(
            self::ALLOWED_TAGS,
            self::ALLOWED_FILTERS,
            self::ALLOWED_METHODS,
            self::ALLOWED_PROPERTIES,
            self::ALLOWED_FUNCTIONS
        );
    }
}
