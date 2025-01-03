<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\PartDBInfo;
use App\Services\Misc\GitVersionInfo;
use App\Services\System\BannerHelper;
use Shivas\VersioningBundle\Service\VersionManagerInterface;

class PartDBInfoProvider implements ProviderInterface
{

    public function __construct(private readonly VersionManagerInterface $versionManager,
        private readonly GitVersionInfo $gitVersionInfo,
        private readonly string $partdb_title,
        private readonly string $base_currency,
        private readonly BannerHelper $bannerHelper,
        private readonly string $default_uri,
        private readonly string $global_timezone,
        private readonly string $global_locale
    )
    {

    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return new PartDBInfo(
            version: $this->versionManager->getVersion()->toString(),
            git_branch: $this->gitVersionInfo->getGitBranchName(),
            git_commit: $this->gitVersionInfo->getGitCommitHash(),
            title: $this->partdb_title,
            banner: $this->bannerHelper->getBanner(),
            default_uri: $this->default_uri,
            global_timezone: $this->global_timezone,
            base_currency: $this->base_currency,
            global_locale: $this->global_locale,
        );
    }
}
