<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TogglePasswordTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly ?TranslatorInterface $translator)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [PasswordType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'toggle' => false,
            'hidden_label' => 'Hide',
            'visible_label' => 'Show',
            'hidden_icon' => 'Default',
            'visible_icon' => 'Default',
            'button_classes' => ['toggle-password-button'],
            'toggle_container_classes' => ['toggle-password-container'],
            'toggle_translation_domain' => null,
            'use_toggle_form_theme' => true,
        ]);

        $resolver->setNormalizer(
            'toggle_translation_domain',
            static fn (Options $options, $labelTranslationDomain) => $labelTranslationDomain ?? $options['translation_domain'],
        );

        $resolver->setAllowedTypes('toggle', ['bool']);
        $resolver->setAllowedTypes('hidden_label', ['string', TranslatableMessage::class, 'null']);
        $resolver->setAllowedTypes('visible_label', ['string', TranslatableMessage::class, 'null']);
        $resolver->setAllowedTypes('hidden_icon', ['string', 'null']);
        $resolver->setAllowedTypes('visible_icon', ['string', 'null']);
        $resolver->setAllowedTypes('button_classes', ['string[]']);
        $resolver->setAllowedTypes('toggle_container_classes', ['string[]']);
        $resolver->setAllowedTypes('toggle_translation_domain', ['string', 'bool', 'null']);
        $resolver->setAllowedTypes('use_toggle_form_theme', ['bool']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['toggle'] = $options['toggle'];

        if (!$options['toggle']) {
            return;
        }

        if ($options['use_toggle_form_theme']) {
            array_splice($view->vars['block_prefixes'], -1, 0, 'toggle_password');
        }

        $controllerName = 'toggle-password';
        $controllerValues = [];
        $view->vars['attr']['data-controller'] = trim(\sprintf('%s %s', $view->vars['attr']['data-controller'] ?? '', $controllerName));

        if (false !== $options['toggle_translation_domain']) {
            $controllerValues['hidden-label'] = $this->translateLabel($options['hidden_label'], $options['toggle_translation_domain']);
            $controllerValues['visible-label'] = $this->translateLabel($options['visible_label'], $options['toggle_translation_domain']);
        } else {
            $controllerValues['hidden-label'] = $options['hidden_label'];
            $controllerValues['visible-label'] = $options['visible_label'];
        }

        $controllerValues['hidden-icon'] = $options['hidden_icon'];
        $controllerValues['visible-icon'] = $options['visible_icon'];
        $controllerValues['button-classes'] = json_encode($options['button_classes'], \JSON_THROW_ON_ERROR);

        foreach ($controllerValues as $name => $value) {
            $view->vars['attr'][\sprintf('data-%s-%s-value', $controllerName, $name)] = $value;
        }

        $view->vars['toggle_container_classes'] = $options['toggle_container_classes'];
    }

    private function translateLabel(string|TranslatableMessage|null $label, ?string $translationDomain): ?string
    {
        if (null === $this->translator || null === $label) {
            return $label;
        }

        if ($label instanceof TranslatableMessage) {
            return $label->trans($this->translator);
        }

        return $this->translator->trans($label, domain: $translationDomain);
    }
}
