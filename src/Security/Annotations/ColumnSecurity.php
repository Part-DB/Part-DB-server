<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Security\Annotations;

use App\Entity\Base\NamedDBElement;
use DateTime;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Collections\ArrayCollection;
use InvalidArgumentException;
use function is_string;

/**
 * @Annotation
 *
 * @Annotation\Target("PROPERTY")
 *
 * With these annotation you can restrict the access to certain coloumns in entities.
 * The entity which should use this class has to use ElementListener as EntityListener.
 */
class ColumnSecurity
{
    /**
     * @var string The name of the edit permission
     */
    public $edit = 'edit';
    /**
     * @var string The name of the read permission
     */
    public $read = 'read';

    /**
     * @var string A prefix for all permission names (e.g..edit, useful for Parts)
     */
    public $prefix = '';

    /**
     * @var string the placeholder that should be used, when the access to the property is denied
     */
    public $placeholder = null;

    public $subject = null;

    /**
     * @var string The name of the property. This is used to determine the default placeholder.
     * @Annotation\Enum({"integer", "string", "object", "boolean", "datetime", "collection"})
     */
    public $type = 'string';

    public function getReadOperationName(): string
    {
        if ('' !== $this->prefix) {
            return $this->prefix.'.'.$this->read;
        }

        return $this->read;
    }

    public function getEditOperationName(): string
    {
        if ('' !== $this->prefix) {
            return $this->prefix.'.'.$this->edit;
        }

        return $this->edit;
    }

    public function getPlaceholder()
    {
        //Check if a class name was specified
        if (class_exists($this->type)) {
            $object = new $this->type();
            if ($object instanceof NamedDBElement) {
                if (is_string($this->placeholder) && '' !== $this->placeholder) {
                    $object->setName($this->placeholder);
                } else {
                    $object->setName('???');
                }
            }

            return $object;
        }

        if (null === $this->placeholder) {
            switch ($this->type) {
                case 'integer':
                case 'int':
                    return 0;
                case 'float':
                    return 0.0;
                case 'string':
                    return '???';
                case 'object':
                    return null;
                case 'collection':
                    return new ArrayCollection();
                case 'boolean':
                case 'bool':
                    return false;
                case 'datetime':
                    return (new DateTime())->setTimestamp(0);
                default:
                    throw new InvalidArgumentException('Unknown type! You have to specify a placeholder!');
            }
        }

        return $this->placeholder;
    }
}
