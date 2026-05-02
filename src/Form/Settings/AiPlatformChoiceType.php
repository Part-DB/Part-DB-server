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

use App\Services\AI\AIPlatformRegistry;
use App\Services\AI\AIPlatforms;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Allow to choose an AI platform from the enabled platforms in the system. This is used in the settings to choose the default platform for AI features.
 */
final class AiPlatformChoiceType extends AbstractType
{
    public function __construct(private readonly AIPlatformRegistry $platformRegistry)
    {
    }

    public function getParent(): string
    {
        return EnumType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
       $choices = array_map(static fn(string $val) => AIPlatforms::from($val), array_keys($this->platformRegistry->getEnabledPlatforms()));

       $resolver->setDefaults([
           'class' => AIPlatforms::class,
           'choices' => $choices,
           'required' => false,
           'platform_selector_label' => null
       ]);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['attr']['data-platform-selector-label'] = $options['platform_selector_label'] ?? $view->vars['id'].'_label';
    }
}
