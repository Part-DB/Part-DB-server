<?php

namespace App\Twig;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DataSourceNameExtension extends AbstractExtension
{
    private TranslatorInterface $translator;
    private array $dataSourceSynonyms;

    public function __construct(TranslatorInterface $translator, ?array $dataSourceSynonyms)
    {
        $this->translator = $translator;
        $this->dataSourceSynonyms = $dataSourceSynonyms ?? [];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_data_source_name', [$this, 'getDataSourceName']),
        ];
    }

    /**
     * Based on the locale and data source names, gives the right synonym value back or the default translator value.
     */
    public function getDataSourceName(string $dataSourceName, string $defaultKey): string
    {
        $locale = $this->translator->getLocale();

        // Use alternative dataSource synonym (if available)
        if (isset($this->dataSourceSynonyms[$dataSourceName][$locale])) {
            return $this->dataSourceSynonyms[$dataSourceName][$locale];
        }

        // Otherwise return the standard translation
        return $this->translator->trans($defaultKey);
    }
}