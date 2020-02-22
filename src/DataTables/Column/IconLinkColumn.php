<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\DataTables\Column;


use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IconLinkColumn extends AbstractColumn
{

    /**
     * @inheritDoc
     */
    public function normalize($value)
    {
        return $value;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults([
                                   'icon' => 'fas fa-fw fa-edit',
                                   'title' => null,
                                   'href' => null,
                               ]);

        $resolver->setAllowedTypes('title', ['null', 'string', 'callable']);
        $resolver->setAllowedTypes('icon', ['null', 'string', 'callable']);
        $resolver->setAllowedTypes('href', ['null', 'string', 'callable']);
    }

    public function render($value, $context)
    {
        $href = $this->getHref($value, $context);
        $icon = $this->getIcon($value, $context);
        $title = $this->getTitle($value, $context);

        if ($href !== null) {
            return sprintf(
                '<a href="%s" title="%s"><i class="%s"></i></a>',
                $href,
                $title,
                $icon
            );
        }

        return "";
    }

    protected function getHref($value, $context): ?string
    {
        $provider = $this->options['href'];
        if (is_string($provider)) {
            return $provider;
        }
        if (is_callable($provider)) {
            return call_user_func($provider, $value, $context);
        }

        return null;
    }

    protected function getIcon($value, $context): ?string
    {
        $provider = $this->options['icon'];
        if (is_string($provider)) {
            return $provider;
        }
        if (is_callable($provider)) {
            return call_user_func($provider, $value, $context);
        }

        return null;
    }

    protected function getTitle($value, $context): ?string
    {
        $provider = $this->options['title'];
        if (is_string($provider)) {
            return $provider;
        }
        if (is_callable($provider)) {
            return call_user_func($provider, $value, $context);
        }

        return null;
    }
}