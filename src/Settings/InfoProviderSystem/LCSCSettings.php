<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Translation\TranslatableMessage as TM;

#[Settings(label: new TM("settings.ips.lcsc"), description: new TM("settings.ips.lcsc.help"))]
class LCSCSettings
{
    use SettingsTrait;

    #[SettingsParameter(label: new TM("settings.ips.lcsc.enabled"), envVar: "bool:PROVIDER_LCSC_ENABLED")]
    public bool $enabled = false;

    #[SettingsParameter(label: new TM("settings.ips.lcsc.currency"), formType: CurrencyType::class, envVar: "string:PROVIDER_LCSC_CURRENCY")]
    #[Assert\Currency()]
    public string $currency = 'EUR';
}