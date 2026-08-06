<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Generates the RSA keypair required by league/oauth2-server-bundle's AuthorizationServer (for API/MCP
 * app auto-provisioning, see config/packages/league_oauth2_server.yaml). Unlike an inert placeholder,
 * this keypair actually signs the JWT access tokens handed to OAuth clients - keep the private key
 * secret and do not regenerate it while tokens signed with the old one are still outstanding (they
 * would fail validation against the new public key).
 */
#[AsCommand('partdb:oauth:generate-keys', 'Generates the RSA keypair used by the OAuth2 authorization server (for API/MCP app auto-provisioning)')]
class OAuthGenerateKeysCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/oauth2/private.key')]
        private readonly string $privateKeyPath,
        #[Autowire('%kernel.project_dir%/var/oauth2/public.key')]
        private readonly string $publicKeyPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite an existing keypair');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('force') && (is_file($this->privateKeyPath) || is_file($this->publicKeyPath))) {
            $io->error('A keypair already exists. Use --force to overwrite it - note this invalidates every outstanding OAuth2 access token, since they are JWTs signed with the old private key.');

            return Command::FAILURE;
        }

        $dir = \dirname($this->privateKeyPath);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            $io->error(sprintf('Could not create directory "%s".', $dir));

            return Command::FAILURE;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false) {
            $io->error('Could not generate an RSA keypair: '.openssl_error_string());

            return Command::FAILURE;
        }

        openssl_pkey_export($resource, $privateKeyPem);
        $publicKeyPem = openssl_pkey_get_details($resource)['key'];

        file_put_contents($this->privateKeyPath, $privateKeyPem);
        chmod($this->privateKeyPath, 0600);
        file_put_contents($this->publicKeyPath, $publicKeyPem);
        chmod($this->publicKeyPath, 0600);

        $io->success(sprintf('Generated a new OAuth2 server RSA keypair at "%s" / "%s".', $this->privateKeyPath, $this->publicKeyPath));

        if (empty($_ENV['OAUTH2_ENCRYPTION_KEY'])) {
            $io->warning('OAUTH2_ENCRYPTION_KEY is not set. Generate one with: bin/console partdb:oauth:generate-secret');
        }

        return Command::SUCCESS;
    }
}
