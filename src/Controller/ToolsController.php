<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Controller;

use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\Attachments\BuiltinAttachmentsFinder;
use App\Services\Misc\GitVersionInfo;
use App\Services\Misc\DBInfoHelper;
use App\Services\System\UpdateAvailableManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/tools')]
class ToolsController extends AbstractController
{
    #[Route(path: '/reel_calc', name: 'tools_reel_calculator')]
    public function reelCalculator(): Response
    {
        $this->denyAccessUnlessGranted('@tools.reel_calculator');

        return $this->render('tools/reel_calculator/reel_calculator.html.twig');
    }

    #[Route(path: '/server_infos', name: 'tools_server_infos')]
    public function systemInfos(GitVersionInfo $versionInfo, DBInfoHelper $DBInfoHelper,
        AttachmentSubmitHandler $attachmentSubmitHandler, UpdateAvailableManager $updateAvailableManager): Response
    {
        $this->denyAccessUnlessGranted('@system.server_infos');

        return $this->render('tools/server_infos/server_infos.html.twig', [
            //Part-DB section
            'git_branch' => $versionInfo->getGitBranchName(),
            'git_commit' => $versionInfo->getGitCommitHash(),
            'default_locale' => $this->getParameter('partdb.locale'),
            'default_timezone' => $this->getParameter('partdb.timezone'),
            'default_currency' => $this->getParameter('partdb.default_currency'),
            'default_theme' => $this->getParameter('partdb.global_theme'),
            'enabled_locales' => $this->getParameter('partdb.locale_menu'),
            'demo_mode' => $this->getParameter('partdb.demo_mode'),
            'gpdr_compliance' => $this->getParameter('partdb.gdpr_compliance'),
            'use_gravatar' => $this->getParameter('partdb.users.use_gravatar'),
            'email_password_reset' => $this->getParameter('partdb.users.email_pw_reset'),
            'enviroment' => $this->getParameter('kernel.environment'),
            'is_debug' => $this->getParameter('kernel.debug'),
            'email_sender' => $this->getParameter('partdb.mail.sender_email'),
            'email_sender_name' => $this->getParameter('partdb.mail.sender_name'),
            'allow_attachments_downloads' => $this->getParameter('partdb.attachments.allow_downloads'),
            'detailed_error_pages' => $this->getParameter('partdb.error_pages.show_help'),
            'error_page_admin_email' => $this->getParameter('partdb.error_pages.admin_email'),
            'configured_max_file_size' => $this->getParameter('partdb.attachments.max_file_size'),
            'effective_max_file_size' => $attachmentSubmitHandler->getMaximumAllowedUploadSize(),
            'saml_enabled' => $this->getParameter('partdb.saml.enabled'),

            //PHP section
            'php_version' => PHP_VERSION,
            'php_uname' => php_uname('a'),
            'php_sapi' => PHP_SAPI,
            'php_bit_size' => PHP_INT_SIZE * 8,
            'php_extensions' => [...get_loaded_extensions()],
            'php_opcache_enabled' => ini_get('opcache.enable'),
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_post_max_size' => ini_get('post_max_size'),

            //DB section
            'db_type' => $DBInfoHelper->getDatabaseType() ?? 'Unknown',
            'db_version' => $DBInfoHelper->getDatabaseVersion() ?? 'Unknown',
            'db_size' => $DBInfoHelper->getDatabaseSize(),
            'db_name' => $DBInfoHelper->getDatabaseName() ?? 'Unknown',
            'db_user' => $DBInfoHelper->getDatabaseUsername() ?? 'Unknown',

            //New version section
            'new_version_available' => $updateAvailableManager->isUpdateAvailable(),
            'new_version' => $updateAvailableManager->getLatestVersionString(),
            'new_version_url' => $updateAvailableManager->getLatestVersionUrl(),
        ]);
    }

    #[Route(path: '/builtin_footprints', name: 'tools_builtin_footprints_viewer')]
    public function builtInFootprintsViewer(BuiltinAttachmentsFinder $builtinAttachmentsFinder, AttachmentURLGenerator $urlGenerator): Response
    {
        $this->denyAccessUnlessGranted('@tools.builtin_footprints_viewer');

        $grouped_footprints = $builtinAttachmentsFinder->getListOfFootprintsGroupedByFolder();
        $grouped_footprints = array_map(static fn($group) => array_map(static fn($placeholder_filepath) => [
            'filename' => basename((string) $placeholder_filepath),
            'assets_path' => $urlGenerator->placeholderPathToAssetPath($placeholder_filepath),
        ], $group), $grouped_footprints);

        return $this->render('tools/builtin_footprints_viewer/builtin_footprints_viewer.html.twig', [
            'grouped_footprints' => $grouped_footprints,
        ]);
    }

    #[Route(path: '/ic_logos', name: 'tools_ic_logos')]
    public function icLogos(): Response
    {
        $this->denyAccessUnlessGranted('@tools.ic_logos');

        return $this->render('tools/ic_logos/ic_logos.html.twig');
    }
}
