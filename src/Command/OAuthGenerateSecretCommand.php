<?php

declare(strict_types=1);

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

namespace App\Command;

use Defuse\Crypto\Key;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generates a new random value for OAUTH2_ENCRYPTION_KEY (config/packages/league_oauth2_server.yaml),
 * the secret used to encrypt OAuth2 authorization codes and refresh tokens. Only prints the value -
 * it deliberately does not write to .env.local itself, since blindly overwriting an already-configured
 * key would invalidate every outstanding refresh token and any in-flight authorization code.
 */
#[AsCommand('partdb:oauth:generate-secret', 'Generates a new random value for OAUTH2_ENCRYPTION_KEY')]
class OAuthGenerateSecretCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $key = Key::createNewRandomKey()->saveToAsciiSafeString();

        $io->writeln('OAUTH2_ENCRYPTION_KEY='.$key);
        $io->note('Add this line to your .env.local file. Keep the key secret, and do not change it again once the OAuth2 server is in use - doing so invalidates all outstanding refresh tokens and any authorization codes currently in flight.');

        return Command::SUCCESS;
    }
}
