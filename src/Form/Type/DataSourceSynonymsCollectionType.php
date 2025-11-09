<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Intl\Locales;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Flat collection of translation rows.
 * View data: list [{dataSource, locale, translation_singular, translation_plural}, ...]
 * Model data: same structure (list). Optionally expands a nested map to a list.
 */
class DataSourceSynonymsCollectionType extends AbstractType
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    private function flattenStructure(array $modelValue): array
    {
        //If the model is already flattened, return as is
        if (array_is_list($modelValue)) {
            return $modelValue;
        }

        $out = [];
        foreach ($modelValue as $dataSource => $locales) {
            if (!is_array($locales)) {
                continue;
            }
            foreach ($locales as $locale => $translations) {
                if (!is_array($translations)) {
                    continue;
                }
                $out[] = [
                    'dataSource' => $dataSource,
                    'locale' => $locale,
                    'translation_singular' => $translations['singular'] ?? '',
                    'translation_plural' => $translations['plural'] ?? '',
                ];
            }
        }
        return $out;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            //Flatten the structure
            $data = $event->getData();
            $event->setData($this->flattenStructure($data));
        });

        $builder->addModelTransformer(new CallbackTransformer(
            // Model -> View
            $this->flattenStructure(...),
            // View -> Model (keep list; let existing behavior unchanged)
            function (array $viewValue) {
                //Turn our flat list back into the structured array

                foreach ($viewValue as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $dataSource = $row['dataSource'] ?? null;
                    $locale = $row['locale'] ?? null;
                    $translation_singular = $row['translation_singular'] ?? null;
                    $translation_plural = $row['translation_plural'] ?? null;

                    if (!is_string($dataSource) || $dataSource === ''
                        || !is_string($locale) || $locale === ''
                    ) {
                        continue;
                    }

                    $out[$dataSource][$locale] = [
                        'singular' => is_string($translation_singular) ? $translation_singular : '',
                        'plural' => is_string($translation_plural) ? $translation_plural : '',
                    ];
                }

                return $out;
            }
        ));

        // Validation and normalization (duplicates + sorting) during SUBMIT
        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $rows = $event->getData();

            if (!is_array($rows)) {
                return;
            }

            // Duplicate check: (dataSource, locale) must be unique
            $seen = [];
            $hasDuplicate = false;

            foreach ($rows as $idx => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $ds = $row['dataSource'] ?? null;
                $loc = $row['locale'] ?? null;

                if (is_string($ds) && $ds !== '' && is_string($loc) && $loc !== '') {
                    $key = $ds . '|' . $loc;
                    if (isset($seen[$key])) {
                        $hasDuplicate = true;

                        if ($form->has((string)$idx)) {
                            $child = $form->get((string)$idx);

                            if ($child->has('dataSource')) {
                                $child->get('dataSource')->addError(
                                    new FormError($this->translator->trans(
                                        'settings.system.data_source_synonyms.collection_type.duplicate',
                                        [], 'validators'
                                    ))
                                );
                            }
                            if ($child->has('locale')) {
                                $child->get('locale')->addError(
                                    new FormError($this->translator->trans(
                                        'settings.system.data_source_synonyms.collection_type.duplicate',
                                        [], 'validators'
                                    ))
                                );
                            }
                        }
                    } else {
                        $seen[$key] = true;
                    }
                }
            }

            if ($hasDuplicate) {
                return;
            }

            // Overall sort: first by dataSource key, then by localized language name
            $sortable = $rows;

            usort($sortable, static function ($a, $b) {
                $aDs = (string)($a['dataSource'] ?? '');
                $bDs = (string)($b['dataSource'] ?? '');

                $cmpDs = strcasecmp($aDs, $bDs);
                if ($cmpDs !== 0) {
                    return $cmpDs;
                }

                $aLoc = (string)($a['locale'] ?? '');
                $bLoc = (string)($b['locale'] ?? '');

                $aName = Locales::getName($aLoc);
                $bName = Locales::getName($bLoc);

                return strcasecmp($aName, $bName);
            });

            $event->setData($sortable);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['data_sources']);
        $resolver->setAllowedTypes('data_sources', 'array');

        // Defaults for the collection and entry type
        $resolver->setDefaults([
            'entry_type' => DataSourceSynonymRowType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'required' => false,
            'prototype' => true,
            'empty_data' => [],
            'entry_options' => ['label' => false],
            'error_translation_domain' => 'validators',
        ]);

        // Pass data_sources automatically to each row (DataSourceSynonymRowType)
        $resolver->setNormalizer('entry_options', function (Options $options, $value) {
            $value = is_array($value) ? $value : [];
            return $value + ['data_sources' => $options['data_sources']];
        });
    }

    public function getParent(): ?string
    {
        return CollectionType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'datasource_synonyms_collection';
    }
}
