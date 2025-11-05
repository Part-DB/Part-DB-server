<?php

namespace App\Twig;

use App\Services\Misc\DataSourceSynonymResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DataSourceNameExtension extends AbstractExtension
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly DataSourceSynonymResolver $resolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_data_source_name_singular', [$this, 'getDataSourceNameSingular']),
            new TwigFunction('get_data_source_name_plural', [$this, 'getDataSourceNamePlural']),
            new TwigFunction('data_source_name_with_hint', [$this, 'getDataSourceNameWithHint']),
        ];
    }

    /**
     * Returns the singular synonym for the given data source in current locale,
     * or the translated fallback key if no synonym provided.
     */
    public function getDataSourceNameSingular(string $dataSourceName, string $defaultKeySingular): string
    {
        return $this->resolver->displayNameSingular($dataSourceName, $defaultKeySingular, $this->translator->getLocale());
    }

    /**
     * Returns the plural synonym for the given data source in current locale,
     * or the translated fallback key if no synonym provided.
     */
    public function getDataSourceNamePlural(string $dataSourceName, string $defaultKeyPlural): string
    {
        return $this->resolver->displayNamePlural($dataSourceName, $defaultKeyPlural, $this->translator->getLocale());
    }

    /**
     * Like data_source_name, only with a note if a synonym was set (uses translation key 'datasource.synonym').
     */
    public function getDataSourceNameWithHint(string $dataSourceName, string $defaultKey, string $type = 'singular'): string
    {
        $type = $type === 'singular' ? 'singular' : 'plural';

        $resolved = $type === 'singular'
            ? $this->resolver->displayNameSingular($dataSourceName, $defaultKey, $this->translator->getLocale())
            : $this->resolver->displayNamePlural($dataSourceName, $defaultKey, $this->translator->getLocale());

        $fallback = $this->translator->trans($defaultKey);

        if ($resolved !== $fallback) {
            return $this->translator->trans('datasource.synonym', [
                '%name%' => $fallback,
                '%synonym%' => $resolved,
            ]);
        }

        return $fallback;
    }
}
