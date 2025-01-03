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
use App\Entity\Parts\Part;
use App\Services\Misc\GitVersionInfo;
use App\Services\System\BannerHelper;
use App\Services\System\UpdateAvailableManager;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomepageController extends AbstractController
{
    public function __construct(private readonly DataTableFactory $dataTable, private readonly BannerHelper $bannerHelper)
    {
    }



    #[Route(path: '/', name: 'homepage')]
    public function homepage(Request $request, GitVersionInfo $versionInfo, EntityManagerInterface $entityManager,
    UpdateAvailableManager $updateAvailableManager): Response
    {
        $this->denyAccessUnlessGranted('HAS_ACCESS_PERMISSIONS');

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

        $show_first_steps = false;
        //When the user is allowed to create parts and no parts are in the database, show the first steps
        if ($this->isGranted('@parts.create')) {
            $repo = $entityManager->getRepository(Part::class);
            $number_of_parts = $repo->count([]);
            if (0 === $number_of_parts) {
                $show_first_steps = true;
            }
        }

        return $this->render('homepage.html.twig', [
            'banner' => $this->bannerHelper->getBanner(),
            'git_branch' => $versionInfo->getGitBranchName(),
            'git_commit' => $versionInfo->getGitCommitHash(),
            'show_first_steps' => $show_first_steps,
            'datatable' => $table,
            'new_version_available' => $updateAvailableManager->isUpdateAvailable(),
            'new_version' => $updateAvailableManager->getLatestVersionString(),
            'new_version_url' => $updateAvailableManager->getLatestVersionUrl(),
        ]);
    }
}
