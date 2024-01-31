<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Form\Type;

use Symfony\Component\Asset\Packages;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Implements a text type with autocomplete functionality based on a static file, containing a list of autocomplete
 * suggestions.
 * Other values are allowed, but the user can select from the list of suggestions.
 * The file must be located in the public directory!
 */
class StaticFileAutocompleteType extends AbstractType
{
    public function __construct(
        private readonly Packages $assets
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('file');
        $resolver->setAllowedTypes('file', 'string');
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        //Add the data-controller and data-url attributes to the form field
        $view->vars['attr']['data-controller'] = 'elements--static-file-autocomplete';
        $view->vars['attr']['data-url'] = $this->assets->getUrl($options['file']);
    }
}