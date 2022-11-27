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

use App\DataTables\Column\IconLinkColumn;
use App\DataTables\Column\LocaleDateTimeColumn;
use App\DataTables\Column\LogEntryExtraColumn;
use App\DataTables\Column\LogEntryTargetColumn;
use App\DataTables\Column\RevertLogColumn;
use App\DataTables\Filters\AttachmentFilter;
use App\DataTables\Filters\LogFilter;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Contracts\TimeTravelInterface;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\CollectionElementDeleted;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementDeletedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Exceptions\EntityNotSupportedException;
use App\Repository\LogEntryRepository;
use App\Services\ElementTypeNameGenerator;
use App\Services\EntityURLGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Psr\Log\LogLevel;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class LogDataTable implements DataTableTypeInterface
{
    protected ElementTypeNameGenerator $elementTypeNameGenerator;
    protected TranslatorInterface $translator;
    protected UrlGeneratorInterface $urlGenerator;
    protected EntityURLGenerator $entityURLGenerator;
    protected LogEntryRepository $logRepo;
    protected Security $security;

    public function __construct(ElementTypeNameGenerator $elementTypeNameGenerator, TranslatorInterface $translator,
        UrlGeneratorInterface $urlGenerator, EntityURLGenerator $entityURLGenerator, EntityManagerInterface $entityManager, Security $security)
    {
        $this->elementTypeNameGenerator = $elementTypeNameGenerator;
        $this->translator = $translator;
        $this->urlGenerator = $urlGenerator;
        $this->entityURLGenerator = $entityURLGenerator;
        $this->logRepo = $entityManager->getRepository(AbstractLogEntry::class);
        $this->security = $security;
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setDefaults([
            'mode' => 'system_log',
            'filter_elements' => [],
            'filter' => null,
        ]);

        $optionsResolver->setAllowedTypes('filter_elements', ['array', 'object']);
        $optionsResolver->setAllowedTypes('mode', 'string');
        $optionsResolver->setAllowedTypes('filter', ['null', LogFilter::class]);

        $optionsResolver->setNormalizer('filter_elements', static function (Options $options, $value) {
            if (!is_array($value)) {
                return [$value];
            }

            return $value;
        });

        $optionsResolver->setAllowedValues('mode', ['system_log', 'element_history', 'last_activity']);
    }

    public function configure(DataTable $dataTable, array $options): void
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        $dataTable->add('symbol', TextColumn::class, [
            'label' => '',
            'className' => 'no-colvis',
            'render' => static function ($value, AbstractLogEntry $context) {
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

                return sprintf(
                    '<i class="fas fa-fw %s" title="%s"></i>',
                    $symbol,
                    $context->getLevelString()
                );
            },
        ]);

        $dataTable->add('id', TextColumn::class, [
            'label' => $this->translator->trans('log.id'),
            'visible' => false,
        ]);

        $dataTable->add('timestamp', LocaleDateTimeColumn::class, [
            'label' => $this->translator->trans('log.timestamp'),
            'timeFormat' => 'medium',
        ]);

        $dataTable->add('type', TextColumn::class, [
            'label' => $this->translator->trans('log.type'),
            'propertyPath' => 'type',
            'render' => function (string $value, AbstractLogEntry $context) {
                return $this->translator->trans('log.type.'.$value);
            },
        ]);

        $dataTable->add('level', TextColumn::class, [
            'label' => $this->translator->trans('log.level'),
            'visible' => 'system_log' === $options['mode'],
            'propertyPath' => 'levelString',
            'render' => function (string $value, AbstractLogEntry $context) {
                return $this->translator->trans('log.level.'.$value);
            },
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
            },
        ]);

        $dataTable->add('target_type', TextColumn::class, [
            'label' => $this->translator->trans('log.target_type'),
            'visible' => false,
            'render' => function ($value, AbstractLogEntry $context) {
                $class = $context->getTargetClass();
                if (null !== $class) {
                    return $this->elementTypeNameGenerator->getLocalizedTypeLabel($class);
                }

                return '';
            },
        ]);

        $dataTable->add('target', LogEntryTargetColumn::class, [
            'label' => $this->translator->trans('log.target'),
            'show_associated' => 'element_history' !== $options['mode'],
        ]);

        $dataTable->add('extra', LogEntryExtraColumn::class, [
            'label' => $this->translator->trans('log.extra'),
        ]);

        $dataTable->add('timeTravel', IconLinkColumn::class, [
            'label' => '',
            'icon' => 'fas fa-fw fa-eye',
            'href' => function ($value, AbstractLogEntry $context) {
                if (
                    ($context instanceof TimeTravelInterface
                        && $context->hasOldDataInformations())
                    || $context instanceof CollectionElementDeleted
                ) {
                    try {
                        $target = $this->logRepo->getTargetElement($context);
                        if (null !== $target) {
                            return $this->entityURLGenerator->timeTravelURL($target, $context->getTimestamp());
                        }
                    } catch (EntityNotSupportedException $exception) {
                        return null;
                    }
                }

                return null;
            },
            'disabled' => function ($value, AbstractLogEntry $context) {
                return !$this->security->isGranted('show_history', $context->getTargetClass());
            },
        ]);

        $dataTable->add('actionRevert', RevertLogColumn::class, [
            'label' => '',
        ]);

        $dataTable->addOrderBy('timestamp', DataTable::SORT_DESCENDING);

        $dataTable->createAdapter(ORMAdapter::class, [
            'entity' => AbstractLogEntry::class,
            'query' => function (QueryBuilder $builder) use ($options): void {
                $this->getQuery($builder, $options);
            },
            'criteria' => [
                function (QueryBuilder $builder) use ($options): void {
                    $this->buildCriteria($builder, $options);
                },
                new SearchCriteriaProvider(),
            ],
        ]);
    }

    private function buildCriteria(QueryBuilder $builder, array $options): void
    {
        if (!empty($options['filter'])) {
            $filter = $options['filter'];
            $filter->apply($builder);
        }

    }

    protected function getQuery(QueryBuilder $builder, array $options): void
    {
        $builder->select('log')
            ->addSelect('user')
            ->from(AbstractLogEntry::class, 'log')
            ->leftJoin('log.user', 'user');

        /* Do this here as we don't want to show up the global count of all log entries in the footer line, with these modes  */
        if ('last_activity' === $options['mode']) {
            $builder->where('log INSTANCE OF '.ElementCreatedLogEntry::class)
                ->orWhere('log INSTANCE OF '.ElementDeletedLogEntry::class)
                ->orWhere('log INSTANCE OF '.ElementEditedLogEntry::class)
                ->orWhere('log INSTANCE OF '.CollectionElementDeleted::class)
                ->andWhere('log.target_type NOT IN (:disallowed)');

            $builder->setParameter('disallowed', [
                AbstractLogEntry::targetTypeClassToID(User::class),
                AbstractLogEntry::targetTypeClassToID(Group::class),
            ]);
        }

        if (!empty($options['filter_elements'])) {
            foreach ($options['filter_elements'] as $element) {
                /** @var AbstractDBElement $element */

                $target_type = AbstractLogEntry::targetTypeClassToID(get_class($element));
                $target_id = $element->getID();
                $builder->orWhere("log.target_type = ${target_type} AND log.target_id = ${target_id}");
            }
        }
    }
}
