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
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds a `warn_on_unsaved_changes` option to any root form.  When set to true, the Stimulus
 * `common--dirty-form` controller attributes are merged into the form element's HTML
 * attributes, enabling unsaved-change detection without any template boilerplate.
 *
 * Usage in a form type:
 *
 *   public function configureOptions(OptionsResolver $resolver): void
 *   {
 *       $resolver->setDefaults(['warn_on_unsaved_changes' => true]);
 *   }
 *
 * Or per-instance from a controller:
 *
 *   $form = $this->createForm(MyFormType::class, $data, ['warn_on_unsaved_changes' => true]);
 */
class UnsavedChangesExtension extends AbstractTypeExtension
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('warn_on_unsaved_changes', false);
        $resolver->setAllowedTypes('warn_on_unsaved_changes', 'bool');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if (!$options['warn_on_unsaved_changes'] || $view->parent !== null) {
            return;
        }

        $extraAttr = [
            'data-controller' => 'common--dirty-form',
            'data-common--dirty-form-confirm-title-value' => $this->translator->trans('form.dirty_form.unsaved_changes.title'),
            'data-common--dirty-form-confirm-message-value' => $this->translator->trans('form.dirty_form.unsaved_changes.message'),
        ];

        // Merge data-action so existing actions on the form element are preserved.
        $existingAction = $view->vars['attr']['data-action'] ?? '';
        $dirtyActions = 'submit->common--dirty-form#submit reset->common--dirty-form#resetDirtyState';
        $extraAttr['data-action'] = $existingAction !== '' ? $existingAction . ' ' . $dirtyActions : $dirtyActions;

        $view->vars['attr'] = array_merge($view->vars['attr'], $extraAttr);
    }
}
