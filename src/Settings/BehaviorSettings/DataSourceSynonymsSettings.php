<?php

declare(strict_types=1);

namespace App\Settings\BehaviorSettings;

use App\Form\Type\DataSourceSynonymsCollectionType;
use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\ParameterTypes\ArrayType;
use Jbtronics\SettingsBundle\ParameterTypes\StringType;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Translation\TranslatableMessage as TM;

#[Settings(label: new TM("settings.system.data_source_synonyms"))]
#[SettingsIcon("fa-language")]
class DataSourceSynonymsSettings
{
    use SettingsTrait;

    #[SettingsParameter(
        ArrayType::class,
        label: new TM("settings.system.data_source_synonyms.configuration"),
        description: new TM("settings.system.data_source_synonyms.configuration.help"),
        options: ['type' => ArrayType::class, 'options' => ['type' => StringType::class]],
        formType: DataSourceSynonymsCollectionType::class,
        formOptions: [
            'required' => false,
            'data_sources' => [
                'category' => new TM("settings.behavior.data_source_synonyms.category"),
                'storagelocation' => new TM("settings.behavior.data_source_synonyms.storagelocation"),
                'footprint' => new TM("settings.behavior.data_source_synonyms.footprint"),
                'manufacturer' => new TM("settings.behavior.data_source_synonyms.manufacturer"),
                'supplier' => new TM("settings.behavior.data_source_synonyms.supplier"),
                'project' => new TM("settings.behavior.data_source_synonyms.project"),
            ],
        ],
    )]
    #[Assert\Type('array')]
    #[Assert\All([new Assert\Type('array')])]
    public array $dataSourceSynonyms = [
        // flat list of rows, e.g.:
        // ['dataSource' => 'category', 'locale' => 'en', 'translation_singular' => 'Category', 'translation_plural' => 'Categories'],
    ];

    /**
     * Normalize to map form:
     * [dataSource => [locale => ['singular' => string, 'plural' => string]]]
     * No preference/merging is applied; both values are returned as provided (missing ones as empty strings).
     *
     * @return array<string, array<string, array{singular: string, plural: string}>>
     */
    public function getSynonymsAsArray(): array
    {
        $result = [];

        foreach ($this->dataSourceSynonyms as $row) {
            if (!is_array($row)) {
                continue;
            }

            $ds  = $row['dataSource'] ?? null;
            $loc = $row['locale'] ?? null;

            if (!is_string($ds) || $ds === '' || !is_string($loc) || $loc === '') {
                continue;
            }

            // Read both fields independently; do not prefer one over the other.
            $singular = isset($row['translation_singular']) && is_string($row['translation_singular'])
                ? $row['translation_singular'] : '';
            $plural = isset($row['translation_plural']) && is_string($row['translation_plural'])
                ? $row['translation_plural'] : '';

            // For legacy data (optional): if only "text" exists and both fields are empty, keep it as given in both slots or leave empty?
            // Requirement says: no preference, just return values. We therefore do NOT map legacy automatically.
            // If you want to expose legacy "text" as well, handle it outside or migrate data beforehand.

            $result[$ds] ??= [];
            $result[$ds][$loc] = [
                'singular' => $singular,
                'plural' => $plural,
            ];
        }

        return $result;
    }
}
