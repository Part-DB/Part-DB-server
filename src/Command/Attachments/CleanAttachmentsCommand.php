<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Command\Attachments;

use Symfony\Component\Console\Attribute\AsCommand;
use App\Services\Attachments\AttachmentManager;
use App\Services\Attachments\AttachmentPathResolver;
use App\Services\Attachments\AttachmentReverseSearch;
use IntlDateFormatter;
use Locale;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mime\MimeTypes;

use function count;

use const DIRECTORY_SEPARATOR;

#[AsCommand('partdb:attachments:clean-unused|app:clean-attachments', 'Lists (and deletes if wanted) attachments files that are not used anymore (abandoned files).')]
class CleanAttachmentsCommand extends Command
{
    protected MimeTypes $mimeTypeGuesser;

    public function __construct(protected AttachmentManager $attachment_helper, protected AttachmentReverseSearch $reverseSearch, protected AttachmentPathResolver $pathResolver)
    {
        $this->mimeTypeGuesser = new MimeTypes();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command allows to find all files in the media folder which are not associated with an attachment anymore.'.
            ' These files are not needed and can eventually deleted.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $mediaPath = $this->pathResolver->getMediaPath();
        $io->note('The media path is '.$mediaPath);
        $securePath = $this->pathResolver->getSecurePath();
        $io->note('The secure media path is '.$securePath);

        $finder = new Finder();
        //We look for files in the media folder only
        $finder->files()->in([$mediaPath, $securePath]);
        //Ignore image cache folder
        $finder->exclude('cache');

        //Ignore automigration folder
        $finder->exclude('.automigration-backup');

        $fs = new Filesystem();

        $file_list = [];

        $table = new Table($output);
        $table->setHeaders(['Filename', 'MIME Type', 'Last modified date']);
        $dateformatter = IntlDateFormatter::create(Locale::getDefault(), IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);

        foreach ($finder as $file) {
            //If not attachment object uses this file, print it
            if ([] === $this->reverseSearch->findAttachmentsByFile($file)) {
                $file_list[] = $file;
                $table->addRow([
                    $fs->makePathRelative($file->getPathname(), $mediaPath),
                    $this->mimeTypeGuesser->guessMimeType($file->getPathname()),
                    $dateformatter->format($file->getMTime()),
                ]);
            }
        }

        if ($file_list !== []) {
            $table->render();

            $continue = $io->confirm(sprintf('Found %d abandoned files. Do you want to delete them? This can not be undone!', count($file_list)), false);

            if (!$continue) {
                //We are finished here, when no files should be deleted
                return Command::SUCCESS;
            }

            //Delete the files
            $fs->remove($file_list);
            //Delete empty folders:
            $this->removeEmptySubFolders($mediaPath);

            $io->success('All abandoned files were removed.');
        } else {
            $io->success('No abandoned files found.');
        }

        return Command::SUCCESS;
    }

    /**
     * This function removes all empty folders inside $path. Taken from https://stackoverflow.com/a/1833681.
     *
     * @param  string  $path The path in which the empty folders should be deleted
     */
    protected function removeEmptySubFolders(string $path): bool
    {
        $empty = true;
        foreach (glob($path.DIRECTORY_SEPARATOR.'*') as $file) {
            $empty &= is_dir($file) && $this->removeEmptySubFolders($file);
        }

        return $empty && rmdir($path);
    }
}
