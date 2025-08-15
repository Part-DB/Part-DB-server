<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

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

namespace App\Services\LabelSystem;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Base\AbstractCompany;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\Contracts\TimeStampableInterface;
use App\Entity\LabelSystem\LabelOptions;
use App\Entity\LabelSystem\LabelProcessMode;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\InfoProviderReference;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartAssociation;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\UserSystem\User;
use App\Twig\BarcodeExtension;
use App\Twig\EntityExtension;
use App\Twig\FormatExtension;
use App\Twig\Sandbox\InheritanceSecurityPolicy;
use App\Twig\Sandbox\SandboxedLabelExtension;
use App\Twig\TwigCoreExtension;
use InvalidArgumentException;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Extra\Html\HtmlExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Extra\String\StringExtension;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityPolicyInterface;

/**
 * This service creates a sandboxed twig environment for the label system.
 * @see \App\Tests\Services\LabelSystem\SandboxedTwigFactoryTest
 */
final class SandboxedTwigFactory
{
    private const ALLOWED_TAGS = ['apply', 'autoescape', 'do', 'for', 'if', 'set', 'verbatim', 'with'];
    private const ALLOWED_FILTERS = ['abs', 'batch', 'capitalize', 'column', 'country_name',
        'currency_name', 'currency_symbol', 'date', 'date_modify', 'data_uri', 'default', 'escape', 'filter', 'first', 'format',
        'format_currency', 'format_date', 'format_datetime', 'format_number', 'format_time', 'html_to_markdown', 'join', 'keys',
        'language_name', 'last', 'length', 'locale_name', 'lower', 'map', 'markdown_to_html', 'merge', 'nl2br', 'raw', 'number_format',
        'reduce', 'replace', 'reverse', 'round', 'slice', 'slug', 'sort', 'spaceless', 'split', 'striptags', 'timezone_name', 'title',
        'trim', 'u', 'upper', 'url_encode',

        //Part-DB specific filters:

        //FormatExtension:
        'format_money', 'format_si', 'format_amount', 'format_bytes',

        //SandboxedLabelExtension
        'placeholders',
        ];

    private const ALLOWED_FUNCTIONS = ['country_names', 'country_timezones', 'currency_names', 'cycle',
        'date', 'html_classes', 'language_names', 'locale_names', 'max', 'min', 'random', 'range', 'script_names',
        'template_from_string', 'timezone_names',

        //Part-DB specific extensions:
        //EntityExtension:
        'entity_type', 'entity_url',
        //BarcodeExtension:
        'barcode_svg',
        //SandboxedLabelExtension
        'placeholder',
        ];

    private const ALLOWED_METHODS = [
        NamedElementInterface::class => ['getName'],
        AbstractDBElement::class => ['getID', '__toString'],
        TimeStampableInterface::class => ['getLastModified', 'getAddedDate'],
        AbstractStructuralDBElement::class => ['isChildOf', 'isRoot', 'getParent', 'getComment', 'getLevel',
            'getFullPath', 'getPathArray', 'getSubelements', 'getChildren', 'isNotSelectable', ],
        AbstractCompany::class => ['getAddress', 'getPhoneNumber', 'getFaxNumber', 'getEmailAddress', 'getWebsite', 'getAutoProductUrl'],
        AttachmentContainingDBElement::class => ['getAttachments', 'getMasterPictureAttachment'],
        Attachment::class => ['isPicture', 'is3DModel', 'hasExternal', 'hasInternal', 'isSecure', 'isBuiltIn', 'getExtension',
            'getElement', 'getExternalPath', 'getHost', 'getFilename', 'getAttachmentType', 'getShowInTable'],
        AbstractParameter::class => ['getFormattedValue', 'getGroup', 'getSymbol', 'getValueMin', 'getValueMax',
            'getValueTypical', 'getUnit', 'getValueText', ],
        MeasurementUnit::class => ['getUnit', 'isInteger', 'useSIPrefix'],
        PartLot::class => ['isExpired', 'getDescription', 'getComment', 'getExpirationDate', 'getStorageLocation',
            'getPart', 'isInstockUnknown', 'getAmount', 'getNeedsRefill', 'getVendorBarcode'],
        StorageLocation::class => ['isFull', 'isOnlySinglePart', 'isLimitToExistingParts', 'getStorageType'],
        Supplier::class => ['getShippingCosts', 'getDefaultCurrency'],
        Part::class => ['isNeedsReview', 'getTags', 'getMass', 'getIpn', 'getProviderReference',
            'getDescription', 'getComment', 'isFavorite', 'getCategory', 'getFootprint',
            'getPartLots', 'getPartUnit', 'useFloatAmount', 'getMinAmount', 'getOrderAmount', 'getOrderDelivery', 'getAmountSum', 'isNotEnoughInstock', 'isAmountUnknown', 'getExpiredAmountSum',
            'getManufacturerProductUrl', 'getCustomProductURL', 'getManufacturingStatus', 'getManufacturer',
            'getManufacturerProductNumber', 'getOrderdetails', 'isObsolete',
            'getParameters', 'getGroupedParameters',
            'isProjectBuildPart', 'getBuiltProject',
            'getAssociatedPartsAsOwner', 'getAssociatedPartsAsOther', 'getAssociatedPartsAll',
            'getEdaInfo'
            ],
        Currency::class => ['getIsoCode', 'getInverseExchangeRate', 'getExchangeRate'],
        Orderdetail::class => ['getPart', 'getSupplier', 'getSupplierPartNr', 'getObsolete',
            'getPricedetails', 'findPriceForQty', 'isObsolete', 'getSupplierProductUrl'],
        Pricedetail::class => ['getOrderdetail', 'getPrice', 'getPricePerUnit', 'getPriceRelatedQuantity',
            'getMinDiscountQuantity', 'getCurrency', 'getCurrencyISOCode'],
        InfoProviderReference:: class => ['getProviderKey', 'getProviderId', 'getProviderUrl', 'getLastUpdated', 'isProviderCreated'],
        PartAssociation::class => ['getType', 'getComment', 'getOwner', 'getOther', 'getOtherType'],

        //Only allow very little information about users...
        User::class => ['isAnonymousUser', 'getUsername', 'getFullName', 'getFirstName', 'getLastName',
            'getDepartment', 'getEmail', ],
    ];
    private const ALLOWED_PROPERTIES = [];

    public function __construct(
        private readonly FormatExtension $formatExtension,
        private readonly BarcodeExtension $barcodeExtension,
        private readonly EntityExtension $entityExtension,
        private readonly TwigCoreExtension $twigCoreExtension,
        private readonly SandboxedLabelExtension $sandboxedLabelExtension,
    )
    {
    }

    public function createTwig(LabelOptions $options): Environment
    {
        if (LabelProcessMode::TWIG !== $options->getProcessMode()) {
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
        $twig->addExtension(new MarkdownExtension());
        $twig->addExtension(new StringExtension());
        $twig->addExtension(new HtmlExtension());

        //Add Part-DB specific extension
        $twig->addExtension($this->formatExtension);
        $twig->addExtension($this->barcodeExtension);
        $twig->addExtension($this->entityExtension);
        $twig->addExtension($this->twigCoreExtension);
        $twig->addExtension($this->sandboxedLabelExtension);

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
