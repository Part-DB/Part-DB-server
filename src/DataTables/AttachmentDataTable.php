<?php
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

namespace App\DataTables;

use App\DataTables\Column\LocaleDateTimeColumn;
use App\Entity\Attachments\Attachment;
use App\Services\Attachments\AttachmentManager;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\ElementTypeNameGenerator;
use App\Services\EntityURLGenerator;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\BoolColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AttachmentDataTable implements DataTableTypeInterface
{
    protected $translator;
    protected $entityURLGenerator;
    protected $attachmentHelper;
    protected $elementTypeNameGenerator;
    protected $attachmentURLGenerator;

    public function __construct(TranslatorInterface $translator, EntityURLGenerator $entityURLGenerator,
                                AttachmentManager $attachmentHelper, AttachmentURLGenerator $attachmentURLGenerator,
                                ElementTypeNameGenerator $elementTypeNameGenerator)
    {
        $this->translator = $translator;
        $this->entityURLGenerator = $entityURLGenerator;
        $this->attachmentHelper = $attachmentHelper;
        $this->elementTypeNameGenerator = $elementTypeNameGenerator;
        $this->attachmentURLGenerator = $attachmentURLGenerator;
    }

    protected function getQuery(QueryBuilder $builder)
    {
        $builder->distinct()->select('attachment')
            ->addSelect('attachment_type')
            //->addSelect('element')
            ->from(Attachment::class, 'attachment')
            ->leftJoin('attachment.attachment_type', 'attachment_type');
        //->leftJoin('attachment.element', 'element');
    }

    public function configure(DataTable $dataTable, array $options)
    {
        $dataTable->add('picture', TextColumn::class, [
            'label' => '',
            'render' => function ($value, Attachment $context) {
                if ($context->isPicture()
                    && !$context->isExternal()
                    && $this->attachmentHelper->isFileExisting($context)) {
                    return sprintf(
                        '<img alt="%s" src="%s" data-thumbnail="%s" class="%s">',
                        'Part image',
                        $this->attachmentURLGenerator->getThumbnailURL($context),
                        $this->attachmentURLGenerator->getThumbnailURL($context, 'thumbnail_md'),
                        'img-fluid hoverpic'
                    );
                }

                return '';
            },
        ]);

        $dataTable->add('name', TextColumn::class, [
            'label' => $this->translator->trans('attachment.edit.name'),
            'render' => function ($value, Attachment $context) {
                //Link to external source
                if ($context->isExternal()) {
                    return sprintf(
                        '<a href="%s" class="link-external">%s</a>',
                        htmlspecialchars($context->getURL()),
                        htmlspecialchars($value)
                    );
                }

                if ($this->attachmentHelper->isFileExisting($context)) {
                    return sprintf(
                        '<a href="%s" target="_blank" data-no-ajax>%s</a>',
                        $this->entityURLGenerator->viewURL($context),
                        htmlspecialchars($value)
                    );
                }

                return $value;
            },
        ]);

        $dataTable->add('attachment_type', TextColumn::class, [
            'label' => $this->translator->trans('attachment.table.type'),
            'field' => 'attachment_type.name',
            'render' => function ($value, Attachment $context) {
                return sprintf(
                    '<a href="%s">%s</a>',
                    $this->entityURLGenerator->editURL($context->getAttachmentType()),
                    htmlspecialchars($value)
                );
            },
        ]);

        $dataTable->add('element', TextColumn::class, [
            'label' => $this->translator->trans('attachment.table.element'),
            //'propertyPath' => 'element.name',
            'render' => function ($value, Attachment $context) {
                return sprintf(
                    '<a href="%s">%s</a>',
                    $this->entityURLGenerator->infoURL($context->getElement()),
                    $this->elementTypeNameGenerator->getTypeNameCombination($context->getElement(), true)
                );
            },
        ]);

        $dataTable->add('filename', TextColumn::class, [
            'propertyPath' => 'filename',
        ]);

        $dataTable->add('filesize', TextColumn::class, [
            'render' => function ($value, Attachment $context) {
                if ($this->attachmentHelper->isFileExisting($context)) {
                    return $this->attachmentHelper->getHumanFileSize($context);
                }
                if ($context->isExternal()) {
                    return '<i>'.$this->translator->trans('attachment.external').'</i>';
                }

                return sprintf(
                    '<span class="badge badge-warning">
                        <i class="fas fa-exclamation-circle fa-fw"></i>%s
                        </span>',
                    $this->translator->trans('attachment.file_not_found')
                );
            },
        ]);

        $dataTable
            ->add('addedDate', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('part.table.addedDate'),
                'visible' => false,
            ])
            ->add('lastModified', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('part.table.lastModified'),
                'visible' => false,
            ]);

        $dataTable->add('show_in_table', BoolColumn::class, [
            'label' => $this->translator->trans('attachment.edit.show_in_table'),
            'trueValue' => $this->translator->trans('true'),
            'falseValue' => $this->translator->trans('false'),
            'nullValue' => '',
            'visible' => false,
        ]);

        $dataTable->add('isPicture', BoolColumn::class, [
            'label' => $this->translator->trans('attachment.edit.isPicture'),
            'trueValue' => $this->translator->trans('true'),
            'falseValue' => $this->translator->trans('false'),
            'nullValue' => '',
            'visible' => false,
            'propertyPath' => 'picture',
        ]);

        $dataTable->add('is3DModel', BoolColumn::class, [
            'label' => $this->translator->trans('attachment.edit.is3DModel'),
            'trueValue' => $this->translator->trans('true'),
            'falseValue' => $this->translator->trans('false'),
            'nullValue' => '',
            'visible' => false,
            'propertyPath' => '3dmodel',
        ]);

        $dataTable->add('isBuiltin', BoolColumn::class, [
            'label' => $this->translator->trans('attachment.edit.isBuiltin'),
            'trueValue' => $this->translator->trans('true'),
            'falseValue' => $this->translator->trans('false'),
            'nullValue' => '',
            'visible' => false,
            'propertyPath' => 'builtin',
        ]);

        $dataTable->createAdapter(ORMAdapter::class, [
            'entity' => Attachment::class,
            'query' => function (QueryBuilder $builder) {
                $this->getQuery($builder);
            },
        ]);
    }
}
