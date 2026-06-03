<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Settings\InfoProviderSystem;

use App\Form\Type\APIKeyType;
use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\ParameterTypes\ArrayType;
use Jbtronics\SettingsBundle\ParameterTypes\StringType;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.ips.conrad"))]
#[SettingsIcon("fa-plug")]
class ConradSettings
{
    use SettingsTrait;

    #[SettingsParameter(label: new TM("settings.ips.element14.apiKey"),
        formType: APIKeyType::class,
        formOptions: ["help_html" => true], envVar: "PROVIDER_CONRAD_API_KEY", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $apiKey = null;

    #[SettingsParameter(label: new TM("settings.ips.conrad.shopID"),
        description: new TM("settings.ips.conrad.shopID.description"),
        formType: EnumType::class,
        formOptions: ['class' => ConradShopIDs::class],
    )]
    public ConradShopIDs $shopID = ConradShopIDs::COM_B2B;

    #[SettingsParameter(label: new TM("settings.ips.reichelt.include_vat"))]
    public bool $includeVAT = true;

    /**
     * @var array|string[] Only attachments in these languages will be downloaded (ISO 639-1 codes)
     */
    #[Assert\Unique()]
    #[Assert\All([new Assert\Language()])]
    #[SettingsParameter(type: ArrayType::class,
        label: new TM("settings.ips.conrad.attachment_language_filter"), description: new TM("settings.ips.conrad.attachment_language_filter.description"),
        options: ['type' => StringType::class],
        formType: LanguageType::class,
        formOptions: [
            'multiple' => true,
            'preferred_choices' => ['en', 'de', 'fr', 'it', 'cs', 'da', 'nl', 'hu', 'hr', 'sk', 'pl']
        ],
    )]
    public array $attachmentLanguageFilter = ['en'];
}
