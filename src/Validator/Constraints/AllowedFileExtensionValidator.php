<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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
use App\Services\Attachments\FileTypeFilterTools;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class AllowedFileExtensionValidator extends ConstraintValidator
{

    protected $filterTools;

    public function __construct(FileTypeFilterTools $filterTools)
    {
        $this->filterTools = $filterTools;
    }

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed $value The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof AllowedFileExtension) {
            throw new UnexpectedTypeException($constraint, AllowedFileExtension::class);
        }

        if ($value instanceof UploadedFile) {
            if ($this->context->getObject() instanceof Attachment) {
                /** @var Attachment $attachment */
                $attachment = $this->context->getObject();
            } elseif ($this->context->getObject() instanceof FormInterface) {
                $attachment = $this->context->getObject()->getParent()->getData();
            } else {
                return;
            }

            $attachment_type = $attachment->getAttachmentType();

            //Only validate if the attachment type has specified an filetype filter:
            if ($attachment_type === null || empty($attachment_type->getFiletypeFilter())) {
                return;
            }

            if (!$this->filterTools->isExtensionAllowed(
                $attachment_type->getFiletypeFilter(),
                $value->getClientOriginalExtension()
            )) {
                $this->context->buildViolation('validator.file_ext_not_allowed')->addViolation();
            }

        }
    }
}