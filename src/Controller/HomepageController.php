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

namespace App\Controller;

use App\DataTables\LogDataTable;
use App\Services\GitVersionInfo;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use const DIRECTORY_SEPARATOR;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;

class HomepageController extends AbstractController
{
    protected $cache;
    protected $kernel;
    protected $dataTable;

    public function __construct(CacheInterface $cache, KernelInterface $kernel, DataTableFactory $dataTable)
    {
        $this->cache = $cache;
        $this->kernel = $kernel;
        $this->dataTable = $dataTable;
    }

    public function getBanner(): string
    {
        $banner = $this->getParameter('banner');
        if (empty($banner)) {
            $banner_path = $this->kernel->getProjectDir()
                .DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'banner.md';

            return file_get_contents($banner_path);
        }

        return $banner;
    }

    /**
     * @Route("/", name="homepage")
     * @param  GitVersionInfo  $versionInfo
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function homepage(Request $request, GitVersionInfo $versionInfo): Response
    {
        if ($this->isGranted("@tools.lastActivity")) {
            $table = $this->dataTable->createFromType(
                LogDataTable::class,
                [
                    'mode' => 'last_activity'
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
            'datatable' => $table
        ]);
    }
}
