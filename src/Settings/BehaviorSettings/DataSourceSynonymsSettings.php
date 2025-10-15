<?php

declare(strict_types=1);

namespace App\Settings\BehaviorSettings;

use App\Form\Type\DataSourceJsonType;
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

    #[SettingsParameter(ArrayType::class,
        label: new TM("settings.system.data_source_synonyms.configuration"),
        description: new TM("settings.system.data_source_synonyms.configuration.help", ['%format%' => '{"en":"", "de":""}']),
        options: ['type' => StringType::class],
        formType: DataSourceJsonType::class,
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
            'default_values' => [
                'category' => '{"en":"Categories", "de":"Kategorien"}',
                'storagelocation' => '{"en":"Storage locations", "de":"Lagerorte"}',
                'footprint' => '{"en":"Footprints", "de":"Footprints"}',
                'manufacturer' => '{"en":"Manufacturers", "de":"Hersteller"}',
                'supplier' => '{"en":"Suppliers", "de":"Lieferanten"}',
                'project' => '{"en":"Projects", "de":"Projekte"}',
            ],
        ],
    )]
    #[Assert\Type('array')]
    public array $dataSourceSynonyms = [
        'category' => '{"en":"Categories", "de":"Kategorien"}',
        'storagelocation' => '{"en":"Storage locations", "de":"Lagerorte"}',
        'footprint' => '{"en":"Footprints", "de":"Footprints"}',
        'manufacturer' => '{"en":"Manufacturers", "de":"Hersteller"}',
        'supplier' => '{"en":"Suppliers", "de":"Lieferanten"}',
        'project' => '{"en":"Projects", "de":"Projekte"}',
    ];

    /**
     * Get the synonyms data as a structured array.
     *
     * @return array<string, array<string, string>> The data source synonyms parsed from JSON to array.
     */
    public function getSynonymsAsArray(): array
    {
        $result = [];
        foreach ($this->dataSourceSynonyms as $key => $jsonString) {
            $result[$key] = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR) ?? [];
        }

        return $result;
    }

}
