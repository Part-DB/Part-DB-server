<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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

use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Metadata\EnvVarMode;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.ips.adafruit"), description: new TM("settings.ips.adafruit.help"))]
#[SettingsIcon("fa-plug")]
class AdafruitSettings
{
    use SettingsTrait;

    #[SettingsParameter(label: new TM("settings.ips.lcsc.enabled"),
        envVar: "bool:PROVIDER_ADAFRUIT_ENABLED", envVarMode: EnvVarMode::OVERWRITE)]
    public bool $enabled = false;

    /**
     * @var bool If true, the product page is retrieved in addition to the product API, to get the image gallery,
     * the technical details, the category and the learn guides. This results in a second request per part.
     */
    #[SettingsParameter(label: new TM("settings.ips.adafruit.fetchProductPage"), description: new TM("settings.ips.adafruit.fetchProductPage.help"),
        envVar: "bool:PROVIDER_ADAFRUIT_FETCH_PRODUCT_PAGE", envVarMode: EnvVarMode::OVERWRITE)]
    public bool $fetchProductPage = true;

    /**
     * @var int The minimum number of seconds between two requests to adafruit.com (0 disables the pacing)
     */
    #[SettingsParameter(label: new TM("settings.ips.adafruit.requestDelay"),
        description: new TM("settings.ips.adafruit.requestDelay.help"),
        formType: NumberType::class,
        formOptions: ["scale" => 0, "attr" => ["min" => 0, "max" => 60]],
        envVar: "int:PROVIDER_ADAFRUIT_REQUEST_DELAY", envVarMode: EnvVarMode::OVERWRITE)]
    #[Assert\Range(min: 0, max: 60)]
    public int $requestDelay = 10;
}
