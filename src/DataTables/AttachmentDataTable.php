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

namespace App\DataTables;

use App\DataTables\Column\LocaleDateTimeColumn;
use App\DataTables\Column\PrettyBoolColumn;
use App\DataTables\Filters\AttachmentFilter;
use App\Entity\Attachments\Attachment;
use App\Services\Attachments\AttachmentManager;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\ElementTypeNameGenerator;
use App\Services\EntityURLGenerator;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AttachmentDataTable implements DataTableTypeInterface
{
    private TranslatorInterface $translator;
    private EntityURLGenerator $entityURLGenerator;
    private AttachmentManager $attachmentHelper;
    private ElementTypeNameGenerator $elementTypeNameGenerator;
    private AttachmentURLGenerator $attachmentURLGenerator;

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

    public function configure(DataTable $dataTable, array $options): void
    {
        $dataTable->add('picture', TextColumn::class, [
            'label' => '',
            'className' => 'no-colvis',
            'render' => function ($value, Attachment $context) {
                if ($context->isPicture()
                    && !$context->isExternal()
                    && $this->attachmentHelper->isFileExisting($context)) {
                    $title = htmlspecialchars($context->getName());
                    if ($context->getFilename()) {
                        $title .= ' ('.htmlspecialchars($context->getFilename()).')';
                    }

                    return sprintf(
                        '<img alt="%s" src="%s" data-thumbnail="%s" class="%s" data-title="%s" data-controller="elements--hoverpic">',
                        'Part image',
                        $this->attachmentURLGenerator->getThumbnailURL($context),
                        $this->attachmentURLGenerator->getThumbnailURL($context, 'thumbnail_md'),
                        'img-fluid hoverpic',
                        $title
                    );
                }

                return '';
            },
        ]);

        $dataTable->add('name', TextColumn::class, [
            'label' => 'attachment.edit.name',
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
            'label' => 'attachment.table.type',
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
            'label' => 'attachment.table.element',
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
            'label' => $this->translator->trans('attachment.table.filename'),
            'propertyPath' => 'filename',
        ]);

        $dataTable->add('filesize', TextColumn::class, [
            'label' => $this->translator->trans('attachment.table.filesize'),
            'render' => function ($value, Attachment $context) {
                if ($this->attachmentHelper->isFileExisting($context)) {
                    return $this->attachmentHelper->getHumanFileSize($context);
                }
                if ($context->isExternal()) {
                    return '<i>'.$this->translator->trans('attachment.external').'</i>';
                }

                return sprintf(
                    '<span class="badge bg-warning">
                        <i class="fas fa-exclamation-circle fa-fw"></i>%s
                        </span>',
                    $this->translator->trans('attachment.file_not_found')
                );
            },
        ]);

        $dataTable
            ->add('addedDate', LocaleDateTimeColumn::class, [
                'label' => 'part.table.addedDate',
                'visible' => false,
            ])
            ->add('lastModified', LocaleDateTimeColumn::class, [
                'label' => 'part.table.lastModified',
                'visible' => false,
            ]);

        $dataTable->add('show_in_table', PrettyBoolColumn::class, [
            'label' => 'attachment.edit.show_in_table',
            'visible' => false,
        ]);

        $dataTable->add('isPicture', PrettyBoolColumn::class, [
            'label' => 'attachment.edit.isPicture',
            'visible' => false,
            'propertyPath' => 'picture',
        ]);

        $dataTable->add('is3DModel', PrettyBoolColumn::class, [
            'label' => 'attachment.edit.is3DModel',
            'visible' => false,
            'propertyPath' => '3dmodel',
        ]);

        $dataTable->add('isBuiltin', PrettyBoolColumn::class, [
            'label' => 'attachment.edit.isBuiltin',
            'visible' => false,
            'propertyPath' => 'builtin',
        ]);

        $dataTable->createAdapter(ORMAdapter::class, [
            'entity' => Attachment::class,
            'query' => function (QueryBuilder $builder): void {
                $this->getQuery($builder);
            },
            'criteria' => [
                function (QueryBuilder $builder) use ($options): void {
                    $this->buildCriteria($builder, $options);
                },
                new SearchCriteriaProvider(),
            ],
        ]);
    }

    private function getQuery(QueryBuilder $builder): void
    {
        $builder->select('attachment')
            ->addSelect('attachment_type')
            //->addSelect('element')
            ->from(Attachment::class, 'attachment')
            ->leftJoin('attachment.attachment_type', 'attachment_type');
        //->leftJoin('attachment.element', 'element');
    }

    private function buildCriteria(QueryBuilder $builder, array $options): void
    {
        //We do the most stuff here in the filter class
        if (isset($options['filter'])) {
            if(!$options['filter'] instanceof AttachmentFilter) {
                throw new \Exception('filter must be an instance of AttachmentFilter!');
            }

            $filter = $options['filter'];
            $filter->apply($builder);
        }

    }
}
