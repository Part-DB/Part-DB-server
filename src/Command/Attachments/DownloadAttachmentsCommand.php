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
use App\Entity\Attachments\AttachmentUpload;
use App\Exceptions\AttachmentDownloadException;
use App\Services\Attachments\AttachmentManager;
use App\Services\Attachments\AttachmentSubmitHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('partdb:attachments:download', "Downloads all attachments which have only an external URL to the local filesystem.")]
class DownloadAttachmentsCommand extends Command
{
    public function __construct(private readonly AttachmentSubmitHandler $attachmentSubmitHandler,
        private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->setHelp('This command downloads all attachments, which only have an external URL, to the local filesystem, so that you have an offline copy of the attachments.');
        $this->addOption('--private', null, null, 'If set, the attachments will be downloaded to the private storage.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('attachment')
            ->from(Attachment::class, 'attachment')
            ->where('attachment.external_path IS NOT NULL')
            ->andWhere('attachment.external_path != \'\'')
            ->andWhere('attachment.internal_path IS NULL');

        $query = $qb->getQuery();
        $attachments = $query->getResult();

        if (count($attachments) === 0) {
            $io->success('No attachments with external URL found.');
            return Command::SUCCESS;
        }

        $io->note('Found ' . count($attachments) . ' attachments with external URL, that will be downloaded.');

        //If the option --private is set, the attachments will be downloaded to the private storage.
        $private = $input->getOption('private');
        if ($private) {
            if (!$io->confirm('Attachments will be downloaded to the private storage. Continue?')) {
                return Command::SUCCESS;
            }
        } else {
            if (!$io->confirm('Attachments will be downloaded to the public storage, where everybody knowing the correct URL can access it. Continue?')){
                return Command::SUCCESS;
            }
        }

        $progressBar = $io->createProgressBar(count($attachments));
        $progressBar->setFormat("%current%/%max% [%bar%] %percent:3s%% %elapsed:16s%/%estimated:-16s% \n%message%");

        $progressBar->setMessage('Starting download...');
        $progressBar->start();


        $errors = [];

        foreach ($attachments as $attachment) {
            /** @var Attachment $attachment */
            $progressBar->setMessage(sprintf('%s (ID: %s) from %s', $attachment->getName(), $attachment->getID(), $attachment->getHost()));
            $progressBar->advance();

            try {
                $attachmentUpload = new AttachmentUpload(file: null, downloadUrl: true, private: $private);
                $this->attachmentSubmitHandler->handleUpload($attachment, $attachmentUpload);

                //Write changes to the database
                $this->entityManager->flush();
            } catch (AttachmentDownloadException $e) {
                $errors[] = [
                    'attachment' => $attachment,
                    'error' => $e->getMessage()
                ];
            }
        }

        $progressBar->finish();

        //Fix the line break after the progress bar
        $io->newLine();
        $io->newLine();

        if (count($errors) > 0) {
            $io->warning('Some attachments could not be downloaded:');
            foreach ($errors as $error) {
                $io->warning(sprintf("Attachment %s (ID %s) could not be downloaded from %s:\n%s",
                    $error['attachment']->getName(),
                    $error['attachment']->getID(),
                    $error['attachment']->getExternalPath(),
                    $error['error'])
                );
            }
        } else {
            $io->success('All attachments downloaded successfully.');
        }

        return Command::SUCCESS;
    }
}