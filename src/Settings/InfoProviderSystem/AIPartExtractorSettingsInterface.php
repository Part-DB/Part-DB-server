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

use App\Services\AI\AIPlatforms;

/**
 * The settings of an info provider, which extracts part information using an AI model (see AIPartInfoExtractor).
 * Use the AIPartExtractorSettingsTrait to implement it.
 */
interface AIPartExtractorSettingsInterface
{
    public function getAIPlatform(): ?AIPlatforms;

    public function getModel(): ?string;

    /**
     * The maximum number of characters of content, which should be passed to the model.
     */
    public function getMaxContentLength(): int;

    /**
     * The language code the output should be in, or null to use the language of the source.
     */
    public function getOutputLanguage(): ?string;

    public function getAdditionalInstructions(): ?string;

    /**
     * Checks if an AI platform and a model are configured, so that the extractor can be used.
     */
    public function isConfigured(): bool;
}
