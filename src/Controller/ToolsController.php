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

use App\Entity\Parts\Part;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\Tools\ComponentValueGuesser;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\Attachments\BuiltinAttachmentsFinder;
use App\Services\Doctrine\DBInfoHelper;
use App\Services\Doctrine\NatsortDebugHelper;
use App\Services\System\GitVersionInfoProvider;
use App\Services\System\UpdateAvailableFacade;
use App\Settings\AppSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Runtime\SymfonyRuntime;

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
    public function systemInfos(GitVersionInfoProvider $versionInfo, DBInfoHelper $DBInfoHelper, NatsortDebugHelper $natsortDebugHelper,
        AttachmentSubmitHandler $attachmentSubmitHandler, UpdateAvailableFacade $updateAvailableManager,
        AppSettings $settings): Response
    {
        $this->denyAccessUnlessGranted('@system.server_infos');

        $oauth_server_enabled = $this->getParameter('partdb.oauth_server.enabled');
        $oauth_dcr_enabled = $this->getParameter('partdb.oauth_server.dcr_enabled');
        //The keypair is required for the OAuth2 server to actually work (see OAuthGenerateKeysCommand);
        //without it OAUTH_SERVER_ENABLED=1 alone does not mean the server is usable.
        $oauth_keypair_exists = is_file($this->getParameter('kernel.project_dir').'/uploads/oauth_private.key')
            && is_file($this->getParameter('kernel.project_dir').'/uploads/oauth_public.key');
        $oauth_encryption_key_set = !empty($_ENV['OAUTH2_ENCRYPTION_KEY']);
        $oauth_fully_configured = $oauth_server_enabled && $oauth_keypair_exists && $oauth_encryption_key_set;

        return $this->render('tools/server_infos/server_infos.html.twig', [
            //Part-DB section
            'git_branch' => $versionInfo->getBranchName(),
            'git_commit' => $versionInfo->getCommitHash(),
            'default_locale' => $settings->system->localization->locale,
            'default_timezone' => $settings->system->localization->timezone,
            'default_currency' => $settings->system->localization->baseCurrency,
            'default_theme' => $settings->system->customization->theme,
            'enabled_locales' => $this->getParameter('partdb.locale_menu'),
            'demo_mode' => $this->getParameter('partdb.demo_mode'),
            'use_gravatar' => $settings->system->privacy->useGravatar,
            'gdpr_compliance' => $this->getParameter('partdb.gdpr_compliance'),
            'email_password_reset' => $this->getParameter('partdb.users.email_pw_reset'),
            'environment' => $this->getParameter('kernel.environment'),
            'is_debug' => $this->getParameter('kernel.debug'),
            'email_sender' => $this->getParameter('partdb.mail.sender_email'),
            'email_sender_name' => $this->getParameter('partdb.mail.sender_name'),
            'allow_attachments_downloads' => $settings->system->attachments->allowDownloads,
            'detailed_error_pages' => $this->getParameter('partdb.error_pages.show_help'),
            'error_page_admin_email' => $this->getParameter('partdb.error_pages.admin_email'),
            'configured_max_file_size' => $settings->system->attachments->maxFileSize,
            'effective_max_file_size' => $attachmentSubmitHandler->getMaximumEffectiveUploadSize(),
            'saml_enabled' => $this->getParameter('partdb.saml.enabled'),
            'oauth_server_enabled' => $oauth_server_enabled,
            'oauth_server_fully_configured' => $oauth_fully_configured,
            'oauth_dcr_enabled' => $oauth_dcr_enabled,
            'oauth_dcr_fully_configured' => $oauth_dcr_enabled && $oauth_fully_configured,

            //PHP section
            'php_version' => PHP_VERSION,
            'php_uname' => php_uname('a'),
            'php_sapi' => PHP_SAPI,
            'php_bit_size' => PHP_INT_SIZE * 8,
            'php_extensions' => [...get_loaded_extensions()],
            'php_opcache_enabled' => ini_get('opcache.enable'),
            'php_upload_max_filesize' => ini_get('upload_max_filesize'),
            'php_post_max_size' => ini_get('post_max_size'),
            'php_max_input_vars' => ini_get('max_input_vars'),
            'kernel_runtime_environment' => $this->getParameter('kernel.runtime_environment'),
            'kernel_runtime_mode' => $this->getParameter('kernel.runtime_mode'),
            'kernel_runtime' => $_SERVER['APP_RUNTIME'] ?? $_ENV['APP_RUNTIME'] ?? SymfonyRuntime::class,

            //DB section
            'db_type' => $DBInfoHelper->getDatabaseType() ?? 'Unknown',
            'db_version' => $DBInfoHelper->getDatabaseVersion() ?? 'Unknown',
            'db_size' => $DBInfoHelper->getDatabaseSize(),
            'db_name' => $DBInfoHelper->getDatabaseName() ?? 'Unknown',
            'db_user' => $DBInfoHelper->getDatabaseUsername() ?? 'Unknown',
            'db_natsort_method' => $natsortDebugHelper->getNaturalSortMethod(),
            'db_natsort_slow_allowed' => $natsortDebugHelper->isSlowNaturalSortAllowed(),

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

    #[Route(path: '/component_image_generator', name: 'tools_component_image_generator')]
    public function componentImageGenerator(Request $request, EntityManagerInterface $em, ComponentValueGuesser $guesser): Response
    {
        $this->denyAccessUnlessGranted('@tools.component_image_generator');

        //Optionally the calculator can be opened in the context of a part, to attach the generated image to it.
        $part = null;
        $partId = $request->query->getInt('part');
        if ($partId > 0) {
            $part = $em->find(Part::class, $partId);
            if ($part !== null) {
                $this->denyAccessUnlessGranted('edit', $part);
            }
        }

        $prefillOhms = null;
        $prefillFarads = null;
        if ($part !== null) {
            [$prefillOhms, $prefillFarads,] = $guesser->extractValue($part);
        }

        return $this->render('tools/component_image_generator/image_generator.html.twig', [
            'part' => $part,
            'prefill_ohms' => $prefillOhms,
            'prefill_farads' => $prefillFarads,
            //When embedded in the part-page modal, render only the calculator inside a Turbo frame.
            'modalMode' => $request->query->getBoolean('modal'),
        ]);
    }

    /**
     * Landing page for the "Generate component images" bulk action: classifies the selected parts
     * (skipping ones that already have a picture or can't be classified) and lets the user review,
     * then generate + attach pictures. Reached from the parts table action bar with ?ids=1,2,3.
     */
    #[Route(path: '/bulk_generate_images', name: 'tools_bulk_generate')]
    public function bulkGenerate(Request $request, EntityManagerInterface $em, ComponentValueGuesser $guesser): Response
    {
        $this->denyAccessUnlessGranted('@tools.component_image_generator');

        $candidates = [];
        $skipped = 0;
        $withPicture = 0;
        //When set, parts that already have a picture are included too (their preview gets overwritten).
        $overwrite = $request->query->getBoolean('overwrite');
        $idsParam = (string) $request->query->get('ids', '');
        $ids = array_values(array_filter(
            array_map(intval(...), explode(',', $idsParam)),
            static fn (int $id): bool => $id > 0
        ));

        if ($ids !== []) {
            foreach ($em->getRepository(Part::class)->findBy(['id' => $ids]) as $part) {
                if (!$this->isGranted('edit', $part)) {
                    continue;
                }
                $hasPicture = $part->getMasterPictureAttachment() !== null;
                //By default only illustrate parts without a picture; in overwrite mode include all.
                if ($hasPicture && !$overwrite) {
                    //Offer a re-generate action only for the ones we could actually classify.
                    if ($guesser->guess($part) !== null) {
                        $withPicture++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }
                $guess = $guesser->guess($part);
                if ($guess === null) {
                    $skipped++;
                    continue;
                }
                $eda = $guesser->edaSuggestion($guess);
                $candidates[] = [
                    'part' => $part,
                    'type' => $guess['type'],
                    'subtype' => $guess['subtype'] ?? null,
                    'marking' => $guess['marking'] ?? null,
                    'value' => $guess['value'],
                    'package' => $guess['package'],
                    'voltage' => $guess['voltage'],
                    'tolerance' => $guess['tolerance'],
                    'pitch' => $guess['pitch'],
                    'diameter' => $guess['diameter'],
                    'power' => $guess['power'],
                    'ppm' => $guess['ppm'],
                    'color' => $guess['color'],
                    'has_picture' => $hasPicture,
                    'kicad_symbol' => $eda['symbol'],
                    'reference_prefix' => $eda['reference'],
                    'kicad_footprint' => $eda['footprint'],
                ];
            }
        }

        $hasCaps = false;
        $hasThtResistors = false;
        $hasSmdResistors = false;
        $hasInductors = false;
        $hasSmdInductors = false;
        $hasSmdCapacitors = false;
        $hasDiodes = false;
        foreach ($candidates as $candidate) {
            if ($candidate['type'] === 'capacitor') {
                $hasCaps = true;
            } elseif ($candidate['type'] === 'resistor') {
                //Power and temperature-coefficient bands only apply to through-hole resistors;
                //SMD chips just carry the printed value code (sized by their package).
                $hasThtResistors = true;
            } elseif ($candidate['type'] === 'smd_resistor') {
                $hasSmdResistors = true;
            } elseif ($candidate['type'] === 'inductor') {
                $hasInductors = true;
            } elseif ($candidate['type'] === 'smd_inductor') {
                $hasSmdInductors = true;
            } elseif ($candidate['type'] === 'smd_capacitor') {
                $hasSmdCapacitors = true;
            } elseif ($candidate['type'] === 'diode') {
                $hasDiodes = true;
            }
        }

        return $this->render('tools/component_image_generator/bulk_generate.html.twig', [
            'candidates' => $candidates,
            'skipped' => $skipped,
            'selected_count' => count($ids),
            'has_caps' => $hasCaps,
            'has_tht_resistors' => $hasThtResistors,
            'has_smd_resistors' => $hasSmdResistors,
            'has_inductors' => $hasInductors,
            'has_smd_inductors' => $hasSmdInductors,
            'has_smd_capacitors' => $hasSmdCapacitors,
            'has_diodes' => $hasDiodes,
            'with_picture' => $withPicture,
            'overwrite' => $overwrite,
            'ids_param' => $idsParam,
        ]);
    }
}
