<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PartDBInfo;
use App\Services\System\BannerHelper;
use App\Services\System\GitVersionInfoProvider;
use App\Settings\SystemSettings\CustomizationSettings;
use App\Settings\SystemSettings\LocalizationSettings;
use Shivas\VersioningBundle\Service\VersionManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class PartDBInfoProvider implements ProviderInterface
{

    public function __construct(private VersionManagerInterface $versionManager,
        private GitVersionInfoProvider $gitVersionInfo,
        private BannerHelper $bannerHelper,
        #[Autowire(param: 'partdb.default_uri')]
        private string $default_uri,
        private LocalizationSettings $localizationSettings,
        private CustomizationSettings $customizationSettings,
    )
    {

    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return new PartDBInfo(
            version: $this->versionManager->getVersion()->toString(),
            git_branch: $this->gitVersionInfo->getBranchName(),
            git_commit: $this->gitVersionInfo->getCommitHash(),
            title: $this->customizationSettings->instanceName,
            banner: $this->bannerHelper->getBanner(),
            default_uri: $this->default_uri,
            global_timezone: $this->localizationSettings->timezone,
            base_currency: $this->localizationSettings->baseCurrency,
            global_locale: $this->localizationSettings->locale,
        );
    }
}
