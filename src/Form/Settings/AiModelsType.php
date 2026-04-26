<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);


namespace App\Form\Settings;

use Symfony\AI\Platform\Capability;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * An text input with autocomplete for AI models from the given platform.
 * The platform is determined by the value of another form field, which is specified by the "platform_selector" option. This allows to filter the available models based on the selected platform.
 */
final class AiModelsType extends AbstractType
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        //The target label of the platform select, which is used to filter the models for the selected platform.
        $resolver->setRequired('platform_selector');
        $resolver->setAllowedTypes('platform_selector', 'string');

        //Only show models, that have the given capability. This is used to only show models that support structured output for the AI extractor settings.
        $resolver->setDefault('filter_capability', null);
        $resolver->setAllowedTypes('filter_capability', ['null', Capability::class]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $urlOptions = ['platform' => '__PLATFORM__'];
        if ($options['filter_capability'] !== null) {
            $urlOptions['capability'] = $options['filter_capability']->value;
        }

        $view->vars['attr']['data-url-template'] = $this->urlGenerator->generate('typeahead_ai_models', $urlOptions);
        $view->vars['attr']['data-controller'] = 'elements--ai-model-autocomplete';

        $view->vars['attr']['data-platform-selector'] = $options['platform_selector'];
    }
}
