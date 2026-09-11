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

use App\Form\InfoProviderSystem\ProviderSelectType;
use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\ParameterTypes\ArrayType;
use Jbtronics\SettingsBundle\ParameterTypes\StringType;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.ips.general"))]
#[SettingsIcon("fa-magnifying-glass")]
class InfoProviderGeneralSettings
{
    /**
     * @var string[]
     */
    #[SettingsParameter(type: ArrayType::class, label: new TM("settings.ips.default_providers"),
        description: new TM("settings.ips.default_providers.help"), options: ['type' => StringType::class],
        formType: ProviderSelectType::class, formOptions: ['input' => 'string', 'required' => false, 'empty_data' => []])]
    public array $defaultSearchProviders = [];

    /**
     * @var int The maximum number of requests Part-DB sends to a single provider within 10 seconds, or 0 for no
     * limit. The default leaves headroom below the shortest window the providers typically enforce, so that an
     * external script sharing the same API account does not push the total over the provider's limit.
     */
    #[SettingsParameter(label: new TM("settings.ips.rate_limit_per_10s"),
        description: new TM("settings.ips.rate_limit_per_10s.help"))]
    #[Assert\Range(min: 0, max: 10000)]
    public int $rateLimitPer10Seconds = 35;

    /**
     * @var int The maximum number of requests Part-DB sends to a single provider within a minute, or 0 for no limit
     */
    #[SettingsParameter(label: new TM("settings.ips.rate_limit_per_minute"),
        description: new TM("settings.ips.rate_limit_per_minute.help"))]
    #[Assert\Range(min: 0, max: 60000)]
    public int $rateLimitPerMinute = 120;

    /**
     * @var int How long a single request may be delayed by the rate limit before it fails instead, in seconds.
     * Bulk operations are happy to wait; this keeps an interactive request from hanging indefinitely.
     */
    #[SettingsParameter(label: new TM("settings.ips.rate_limit_max_wait"),
        description: new TM("settings.ips.rate_limit_max_wait.help"))]
    #[Assert\Range(min: 0, max: 600)]
    public int $rateLimitMaxWait = 30;
}
