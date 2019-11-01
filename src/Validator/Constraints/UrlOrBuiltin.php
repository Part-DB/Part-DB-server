<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony)
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
 *
 */

namespace App\Validator\Constraints;


use App\Entity\Attachments\Attachment;
use Symfony\Component\Validator\Constraints\Url;

/**
 * Constraints the field that way that the content is either a url or a path to a builtin ressource (like %FOOTPRINTS%)
 * @package App\Validator\Constraints
 * @Annotation
 */
class UrlOrBuiltin extends Url
{
    /** @var array A list of the placeholders that are treated as builtin */
    public $allowed_placeholders = Attachment::BUILTIN_PLACEHOLDER;
}