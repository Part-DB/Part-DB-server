<?php
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

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Entity\LogSystem;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * This log entry is created when a user logs in.
 *
 * @ORM\Entity()
 */
class UserLoginLogEntry extends AbstractLogEntry
{
    protected $typeString = 'user_login';

    public function __construct(string $ip_address, bool $anonymize = true)
    {
        parent::__construct();
        $this->level = self::LEVEL_INFO;
        $this->setIPAddress($ip_address, $anonymize);
    }

    /**
     * Return the (anonymized) IP address used to login the user.
     *
     * @return string
     */
    public function getIPAddress(): string
    {
        return $this->extra['i'];
    }

    /**
     * Sets the IP address used to login the user.
     *
     * @param string $ip        The IP address used to login the user.
     * @param bool   $anonymize Anonymize the IP address (remove last block) to be GPDR compliant
     *
     * @return $this
     */
    public function setIPAddress(string $ip, bool $anonymize = true): self
    {
        if ($anonymize) {
            $ip = IpUtils::anonymize($ip);
        }

        $this->extra['i'] = $ip;

        return $this;
    }
}
