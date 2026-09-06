<?php

declare(strict_types=1);

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
namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A textarea rendered as a plain-text code editor with Twig/HTML syntax highlighting and line numbers
 * (via the "elements--code-editor" Stimulus controller), instead of a WYSIWYG rich text editor.
 *
 * This is used instead of RichTextEditorType wherever the content is a Twig template: CKEditor treats
 * its content as HTML and therefore HTML-escapes characters like <, > and & (e.g. in
 * "filter(v => v.id > 1)") as soon as its data is synchronized, which corrupts Twig expressions.
 */
class TwigCodeEditorType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('required', false);
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['attr'] = array_merge($view->vars['attr'], $this->optionsToAttrArray($view->vars['attr']));

        parent::finishView($view, $form, $options);
    }

    protected function optionsToAttrArray(array $existingAttr = []): array
    {
        //Keep any data-controller already set on the field (e.g. by the parent form type) and only add
        //the code editor controller to it, instead of overwriting it.
        $controllers = array_filter([
            $existingAttr['data-controller'] ?? null,
            'elements--code-editor',
        ], static fn (?string $controller): bool => $controller !== null && $controller !== '');

        return [
            //Set novalidate attribute, or we will get problems that form can not be submitted as textarea is not focusable
            'novalidate' => 'novalidate',
            //Add our data-controller element to the textarea
            'data-controller' => implode(' ', $controllers),
        ];
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }
}
