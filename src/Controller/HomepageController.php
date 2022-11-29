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

namespace App\Controller;

use App\DataTables\LogDataTable;
use App\Services\GitVersionInfo;
use const DIRECTORY_SEPARATOR;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;

class HomepageController extends AbstractController
{
    protected CacheInterface $cache;
    protected KernelInterface $kernel;
    protected DataTableFactory $dataTable;

    public function __construct(CacheInterface $cache, KernelInterface $kernel, DataTableFactory $dataTable)
    {
        $this->cache = $cache;
        $this->kernel = $kernel;
        $this->dataTable = $dataTable;
    }

    public function getBanner(): string
    {
        $banner = $this->getParameter('partdb.banner');
        if (empty($banner)) {
            $banner_path = $this->kernel->getProjectDir()
                .DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'banner.md';

            return file_get_contents($banner_path);
        }

        return $banner;
    }

    /**
     * @Route("/", name="homepage")
     */
    public function homepage(Request $request, GitVersionInfo $versionInfo): Response
    {
        if ($this->isGranted('@tools.lastActivity')) {
            $table = $this->dataTable->createFromType(
                LogDataTable::class,
                [
                    'mode' => 'last_activity',
                ],
                ['pageLength' => 10]
            )
                ->handleRequest($request);

            if ($table->isCallback()) {
                return $table->getResponse();
            }
        } else {
            $table = null;
        }

        return $this->render('homepage.html.twig', [
            'banner' => $this->getBanner(),
            'git_branch' => $versionInfo->getGitBranchName(),
            'git_commit' => $versionInfo->getGitCommitHash(),
            'datatable' => $table,
        ]);
    }
}
