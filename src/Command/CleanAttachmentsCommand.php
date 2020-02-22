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
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Command;

use App\Services\Attachments\AttachmentManager;
use App\Services\Attachments\AttachmentPathResolver;
use App\Services\Attachments\AttachmentReverseSearch;
use function count;
use const DIRECTORY_SEPARATOR;
use IntlDateFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mime\MimeTypes;

class CleanAttachmentsCommand extends Command
{
    protected static $defaultName = 'app:clean-attachments';

    protected $attachment_helper;
    protected $reverseSearch;
    protected $mimeTypeGuesser;
    protected $pathResolver;

    public function __construct(AttachmentManager $attachmentHelper, AttachmentReverseSearch $reverseSearch, AttachmentPathResolver $pathResolver)
    {
        $this->attachment_helper = $attachmentHelper;
        $this->pathResolver = $pathResolver;
        $this->reverseSearch = $reverseSearch;
        $this->mimeTypeGuesser = new MimeTypes();
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Lists (and deletes if wanted) attachments files that are not used anymore (abandoned files).')
            ->setHelp('This command allows to find all files in the media folder which are not associated with an attachment anymore.'.
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

        $fs = new Filesystem();

        $file_list = [];

        $table = new Table($output);
        $table->setHeaders(['Filename', 'MIME Type', 'Last modified date']);
        $dateformatter = IntlDateFormatter::create(\Locale::getDefault(), IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);

        foreach ($finder as $file) {
            //If not attachment object uses this file, print it
            if (0 === count($this->reverseSearch->findAttachmentsByFile($file))) {
                $file_list[] = $file;
                $table->addRow([
                    $fs->makePathRelative($file->getPathname(), $mediaPath),
                    $this->mimeTypeGuesser->guessMimeType($file->getPathname()),
                    $dateformatter->format($file->getMTime()),
                ]);
            }
        }

        if (count($file_list) > 0) {
            $table->render();

            $continue = $io->confirm(sprintf('Found %d abandoned files. Do you want to delete them? This can not be undone!', count($file_list)), false);

            if (! $continue) {
                //We are finished here, when no files should be deleted
                return 0;
            }

            //Delete the files
            $fs->remove($file_list);
            //Delete empty folders:
            $this->removeEmptySubFolders($mediaPath);

            $io->success('All abandoned files were removed.');
        } else {
            $io->success('No abandoned files found.');
        }

        return 0;
    }

    /**
     * This function removes all empty folders inside $path. Taken from https://stackoverflow.com/a/1833681.
     *
     * @param string $path The path in which the empty folders should be deleted
     *
     * @return bool
     */
    protected function removeEmptySubFolders($path)
    {
        $empty = true;
        foreach (glob($path.DIRECTORY_SEPARATOR.'*') as $file) {
            $empty &= is_dir($file) && $this->removeEmptySubFolders($file);
        }

        return $empty && rmdir($path);
    }
}
