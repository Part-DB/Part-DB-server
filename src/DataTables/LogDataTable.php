<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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


use App\DataTables\Column\EntityColumn;
use App\DataTables\Column\LocaleDateTimeColumn;
use App\DataTables\Column\LogEntryTargetColumn;
use App\Entity\Attachments\Attachment;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\UserSystem\User;
use App\Services\ElementTypeNameGenerator;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Psr\Log\LogLevel;
use SebastianBergmann\CodeCoverage\Report\Text;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LogDataTable implements DataTableTypeInterface
{
    protected $elementTypeNameGenerator;
    protected $translator;
    protected $urlGenerator;

    public function __construct(ElementTypeNameGenerator $elementTypeNameGenerator, TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator)
    {
        $this->elementTypeNameGenerator = $elementTypeNameGenerator;
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
    }

    public function configure(DataTable $dataTable, array $options)
    {
        $dataTable->add('symbol', TextColumn::class, [
            'label' => '',
            'render' => function ($value, AbstractLogEntry $context) {
                switch ($context->getLevelString()) {
                    case LogLevel::DEBUG:
                        $symbol = 'fa-bug';
                        break;
                    case LogLevel::INFO:
                        $symbol = 'fa-info';
                        break;
                    case LogLevel::NOTICE:
                        $symbol = 'fa-flag';
                        break;
                    case LogLevel::WARNING:
                        $symbol = 'fa-exclamation-circle';
                        break;
                    case LogLevel::ERROR:
                        $symbol = 'fa-exclamation-triangle';
                        break;
                    case LogLevel::CRITICAL:
                        $symbol = 'fa-bolt';
                        break;
                    case LogLevel::ALERT:
                        $symbol = 'fa-radiation';
                        break;
                    case LogLevel::EMERGENCY:
                        $symbol = 'fa-skull-crossbones';
                        break;
                    default:
                        $symbol = 'fa-question-circle';
                        break;
                }

                return sprintf('<i class="fas fa-fw %s"></i>', $symbol);
            }
        ]);

        $dataTable->add('id', TextColumn::class, [
            'label' => $this->translator->trans('log.id'),
            'visible' => false,
        ]);

        $dataTable->add('timestamp', LocaleDateTimeColumn::class, [
            'label' => $this->translator->trans('log.timestamp'),
            'timeFormat' => 'medium'
        ]);

        $dataTable->add('type', TextColumn::class, [
            'label' => $this->translator->trans('log.type'),
            'propertyPath' => 'type',
            'render' => function (string $value, AbstractLogEntry $context) {
                return $this->translator->trans('log.type.' . $value);
            }

        ]);

        $dataTable->add('level', TextColumn::class, [
            'label' => $this->translator->trans('log.level'),
            'propertyPath' => 'levelString',
            'render' => function (string $value, AbstractLogEntry $context) {
                return $value;
            }
        ]);


        $dataTable->add('user', TextColumn::class, [
            'label' => $this->translator->trans('log.user'),
            'render' => function ($value, AbstractLogEntry $context) {
                $user = $context->getUser();
                return sprintf(
                    '<a href="%s">%s</a>',
                    $this->urlGenerator->generate('user_info', ['id' => $user->getID()]),
                    $user->getFullName(true)
                );
            }
        ]);



        $dataTable->add('target_type', TextColumn::class, [
            'label' => $this->translator->trans('log.target_type'),
            'visible' => false,
            'render' => function ($value, AbstractLogEntry $context) {
                $class = $context->getTargetClass();
                if ($class !== null) {
                    return $this->elementTypeNameGenerator->getLocalizedTypeLabel($class);
                }
                return '';
            }
        ]);

        $dataTable->add('target', LogEntryTargetColumn::class, [
            'label' => $this->translator->trans('log.target')
        ]);

        $dataTable->addOrderBy('timestamp', DataTable::SORT_DESCENDING);

        $dataTable->createAdapter(ORMAdapter::class, [
            'entity' => AbstractLogEntry::class,
            'query' => function (QueryBuilder $builder): void {
                $this->getQuery($builder);
            },
        ]);
    }

    protected function getQuery(QueryBuilder $builder): void
    {
        $builder->distinct()->select('log')
            ->addSelect('user')
            ->from(AbstractLogEntry::class, 'log')
            ->leftJoin('log.user', 'user');
    }
}