<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PartDBInfo;
use App\Services\Misc\GitVersionInfo;
use App\Services\System\BannerHelper;
use App\Settings\SystemSettings\CustomizationSettings;
use App\Settings\SystemSettings\LocalizationSettings;
use Shivas\VersioningBundle\Service\VersionManagerInterface;

class PartDBInfoProvider implements ProviderInterface
{

    public function __construct(private readonly VersionManagerInterface $versionManager,
        private readonly GitVersionInfo $gitVersionInfo,
        private readonly BannerHelper $bannerHelper,
        private readonly string $default_uri,
        private readonly LocalizationSettings $localizationSettings,
        private readonly CustomizationSettings $customizationSettings,
    )
    {

    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return new PartDBInfo(
            version: $this->versionManager->getVersion()->toString(),
            git_branch: $this->gitVersionInfo->getGitBranchName(),
            git_commit: $this->gitVersionInfo->getGitCommitHash(),
            title: $this->customizationSettings->instanceName,
            banner: $this->bannerHelper->getBanner(),
            default_uri: $this->default_uri,
            global_timezone: $this->localizationSettings->timezone,
            base_currency: $this->localizationSettings->baseCurrency,
            global_locale: $this->localizationSettings->locale,
        );
    }
}
