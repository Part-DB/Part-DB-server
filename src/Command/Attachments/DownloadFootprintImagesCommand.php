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


namespace App\Command\Attachments;

use App\Entity\Attachments\Attachment;
use App\Services\Attachments\AttachmentPathResolver;
use App\Services\Attachments\BuiltinAttachmentsFinder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand('partdb:attachments:download-footprint-images', 'Downloads the KiCad footprint images into the custom footprints folder.')]
class DownloadFootprintImagesCommand extends Command
{
    private const DOWNLOAD_URL = 'https://github.com/Part-DB/kicad-footprint-images/releases/latest/download/KiCad.zip';

    /**
     * The folder inside the ZIP archive, which contains the images. It is extracted as subfolder of the custom footprints folder
     */
    private const ARCHIVE_FOLDER = 'KiCad';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AttachmentPathResolver $pathResolver,
        private readonly BuiltinAttachmentsFinder $builtinAttachmentsFinder,
    )
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->setHelp('This command downloads the rendered images of the KiCad footprint library from '
            .self::DOWNLOAD_URL.' and extracts them into the custom footprints folder, where they can be used via the %FOOTPRINTS_C% placeholder.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $custom_path = $this->pathResolver->getCustomFootprintsPath();
        if ($custom_path === null) {
            $io->error('The custom footprints folder (public/custom/footprints) does not exist. Please create it first.');
            return Command::FAILURE;
        }

        $target_path = $custom_path.'/'.self::ARCHIVE_FOLDER;
        $fs = new Filesystem();

        $io->note('The KiCad footprint images (about 50 MB) will be downloaded from '.self::DOWNLOAD_URL.' and extracted to '.$target_path);
        if ($fs->exists($target_path)) {
            $io->warning('The folder '.$target_path.' already exists and will be replaced. All files in it will be deleted!');
        }
        if (!$io->confirm('Continue?')) {
            return Command::SUCCESS;
        }

        $tmp_file = $fs->tempnam(sys_get_temp_dir(), 'partdb_footprints', '.zip');

        try {
            $this->download($io, $tmp_file);

            $zip = new \ZipArchive();
            if ($zip->open($tmp_file) !== true) {
                $io->error('The downloaded file is not a valid ZIP archive.');
                return Command::FAILURE;
            }

            $entries = $this->getEntriesToExtract($zip);
            if ($entries === []) {
                $zip->close();
                $io->error('The downloaded archive does not contain any footprint images.');
                return Command::FAILURE;
            }

            $io->text('Extracting '.count($entries).' files...');
            $fs->remove($target_path);
            $success = $zip->extractTo($custom_path, $entries);
            $zip->close();
            if (!$success) {
                $io->error('Could not extract the archive to '.$custom_path.'. Check the file permissions.');
                return Command::FAILURE;
            }
        } finally {
            $fs->remove($tmp_file);
        }

        //Make the new images visible in the gallery and the attachment suggestions
        $this->builtinAttachmentsFinder->clearCache();

        $io->success('The KiCad footprint images were downloaded. They can be used via %FOOTPRINTS_C%/'.self::ARCHIVE_FOLDER.'/...');

        return Command::SUCCESS;
    }

    private function download(SymfonyStyle $io, string $target_file): void
    {
        $progressBar = $io->createProgressBar();
        $progressBar->setFormat(' %current_mb%/%max_mb% MB [%bar%] %percent:3s%%');
        $progressBar->setMessage('0', 'current_mb');
        $progressBar->setMessage('?', 'max_mb');

        $response = $this->httpClient->request('GET', self::DOWNLOAD_URL, [
            'on_progress' => static function (int $downloaded, int $total) use ($progressBar): void {
                if ($total > 0) {
                    $progressBar->setMaxSteps($total);
                    $progressBar->setMessage(number_format($total / 1024 / 1024, 1), 'max_mb');
                }
                $progressBar->setMessage(number_format($downloaded / 1024 / 1024, 1), 'current_mb');
                $progressBar->setProgress($downloaded);
            },
        ]);

        $handle = fopen($target_file, 'wb');
        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($handle, $chunk->getContent());
            }
        } finally {
            fclose($handle);
        }

        $progressBar->finish();
        $io->newLine(2);
    }

    /**
     * Returns the names of all entries of the archive, which should be extracted: Only pictures inside ARCHIVE_FOLDER,
     * so that a (malicious) archive can not write files outside of it or place executable files in the public folder.
     * @return string[]
     */
    private function getEntriesToExtract(\ZipArchive $zip): array
    {
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || !str_starts_with($name, self::ARCHIVE_FOLDER.'/') || str_contains($name, '..')
                || str_contains($name, '\\')) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($extension, Attachment::PICTURE_EXTS, true) && $extension !== 'svg') {
                $entries[] = $name;
            }
        }

        return $entries;
    }
}
