<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Settings\BehaviorSettings\DataSourceSynonymsSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A form type that generates multiple JSON input fields for different data sources.
 */
class DataSourceJsonType extends AbstractType
{
    public function __construct(private DataSourceSynonymsSettings $settings)
    {

    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $dataSources = $options['data_sources'];
        $defaultValues = $options['default_values'];
        $existingData = $options['data'] ?? [];

        if ($existingData === []) {
            $existingData = $this->settings->dataSourceSynonyms;
        }

        foreach ($dataSources as $key => $label) {
            $initialData = $existingData[$key] ?? $defaultValues[$key] ?? '{}';

            $builder->add($key, TextareaType::class, [
                'label' => $label,
                'required' => false,
                'data' => $initialData,
                'attr' => [
                    'rows' => 3,
                    'style' => 'font-family: monospace;',
                    'placeholder' => sprintf('%s translations in JSON format', ucfirst($key)),
                ],
                'constraints' => [
                    new Assert\Callback(function ($value, $context) {
                        if ($value && !static::isValidJson($value)) {
                            $context->buildViolation('The field must contain valid JSON.')->addViolation();
                        }
                    }),
                ],
            ]);
        }

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($defaultValues) {
            $data = $event->getData();

            if (!$data) {
                $event->setData($defaultValues);
                return;
            }

            foreach ($defaultValues as $key => $defaultValue) {
                if (empty($data[$key])) {
                    $data[$key] = $defaultValue;
                } else {
                    $decodedValue = json_decode($data[$key], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data[$key] = json_encode($decodedValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    }
                }
            }

            $event->setData($data);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_sources' => [],
            'default_values' => [],
        ]);

        $resolver->setAllowedTypes('data_sources', 'array');
        $resolver->setAllowedTypes('default_values', 'array');
    }

    /**
     * Validates if a string is a valid JSON format.
     *
     * @param string $json
     * @return bool
     */
    public static function isValidJson(string $json): bool
    {
        json_decode($json);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
