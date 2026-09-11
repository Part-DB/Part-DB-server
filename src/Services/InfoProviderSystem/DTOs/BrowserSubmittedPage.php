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

namespace App\Services\InfoProviderSystem\DTOs;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Represents a webpage submitted by the browser extension, held temporarily in the application cache.
 */
final readonly class BrowserSubmittedPage
{
    /**
     * @var string A unique token for this page, derived from the URL and HTML content. Used to identify the page in the cache without storing the full HTML in the session.
     */
    public string $token;

    public function __construct(
        #[Assert\Url()]
        #[Assert\NotBlank]
        public string $url,
        #[Assert\NotBlank]
        #[Assert\Length(max: 5 * 1024 * 1024)] // Limit to 5 MB to prevent abuse
        public string $html,
        #[Assert\NotBlank]
        public string $title,
        public \DateTimeImmutable $submittedAt = new \DateTimeImmutable(),
    ) {
        $this->token = hash('xxh3', $url . '|' . $html);
    }
}
