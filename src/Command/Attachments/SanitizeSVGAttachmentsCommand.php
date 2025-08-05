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


namespace App\Command\Attachments;

use App\Entity\Attachments\Attachment;
use App\Services\Attachments\AttachmentSubmitHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('partdb:attachments:sanitize-svg', "Sanitize uploaded SVG files.")]
class SanitizeSVGAttachmentsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly AttachmentSubmitHandler $attachmentSubmitHandler, ?string $name = null)
    {
        parent::__construct($name);
    }

    public function configure(): void
    {
        $this->setHelp('This command allows to sanitize SVG files uploaded via attachments. This happens automatically since version 1.17.1, this command is intended to be used for older files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('This command will sanitize all uploaded SVG files. This is only required if you have uploaded (untrusted) SVG files before version 1.17.1. If you are running a newer version, you don\'t need to run this command (again).');
        if (!$io->confirm('Do you want to continue?', false)) {
            $io->success('Command aborted.');
            return Command::FAILURE;
        }

        $io->info('Sanitizing SVG files...');

        //Finding all attachments with svg files
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(Attachment::class, 'a')
            ->where('a.internal_path LIKE :pattern ESCAPE \'#\'')
            ->orWhere('a.original_filename LIKE :pattern ESCAPE \'#\'')
            ->setParameter('pattern', '%.svg');

        $attachments = $qb->getQuery()->getResult();
        $io->note('Found '.count($attachments).' attachments with SVG files.');

        if (count($attachments) === 0) {
            $io->success('No SVG files found.');
            return Command::FAILURE;
        }

        $io->info('Sanitizing SVG files...');
        $io->progressStart(count($attachments));
        foreach ($attachments as $attachment) {
            /** @var Attachment $attachment */
            $io->note('Sanitizing attachment '.$attachment->getId().' ('.($attachment->getFilename() ?? '???').')');
            $this->attachmentSubmitHandler->sanitizeSVGAttachment($attachment);
            $io->progressAdvance();

        }
        $io->progressFinish();

        $io->success('Sanitization finished. All SVG files have been sanitized.');
        return Command::SUCCESS;
    }
}