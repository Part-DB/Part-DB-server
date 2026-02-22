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
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.ips.canopy"))]
#[SettingsIcon("fa-plug")]
class CanopySettings
{
    public const ALLOWED_DOMAINS = [
        "amazon.de" => "DE",
        "amazon.com" => "US",
        "amazon.co.uk" => "UK",
        "amazon.fr" => "FR",
        "amazon.it" => "IT",
        "amazon.es" => "ES",
        "amazon.ca" => "CA",
        "amazon.com.au" => "AU",
        "amazon.com.br" => "BR",
        "amazon.com.mx" => "MX",
        "amazon.in" => "IN",
        "amazon.co.jp" => "JP",
        "amazon.nl" => "NL",
        "amazon.pl" => "PL",
        "amazon.sa" => "SA",
        "amazon.sg" => "SG",
        "amazon.se" => "SE",
        "amazon.com.tr" => "TR",
        "amazon.ae" => "AE",
        "amazon.com.be" => "BE",
        "amazon.com.cn" => "CN",
    ];

    use SettingsTrait;

    #[SettingsParameter(label: new TM("settings.ips.mouser.apiKey"),
        formType: APIKeyType::class,
        formOptions: ["help_html" => true], envVar: "PROVIDER_CANOPY_API_KEY", envVarMode: EnvVarMode::OVERWRITE)]
    public ?string $apiKey = null;

    /**
     * @var string The domain used internally for the API requests. This is not necessarily the same as the domain shown to the user, which is determined by the keys of the ALLOWED_DOMAINS constant
     */
    #[SettingsParameter(label: new TM("settings.ips.tme.country"), formType: ChoiceType::class, formOptions: ["choices" => self::ALLOWED_DOMAINS])]
    public string $domain = "DE";

    /**
     * @var bool If true, the provider will always retrieve details for a part, resulting in an additional API request
     */
    #[SettingsParameter(label: new TM("settings.ips.canopy.alwaysGetDetails"), description: new TM("settings.ips.canopy.alwaysGetDetails.help"))]
    public bool $alwaysGetDetails = false;

    /**
     * Returns the real domain (e.g. amazon.de) based on the selected domain (e.g. DE)
     * @return string
     */
    public function getRealDomain(): string
    {
        $domain = array_search($this->domain, self::ALLOWED_DOMAINS);
        if ($domain === false) {
            throw new \InvalidArgumentException("Invalid domain selected");
        }
        return $domain;
    }
}
