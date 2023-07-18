<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\DataTables;

use App\DataTables\Column\EnumColumn;
use App\Entity\LogSystem\LogTargetType;
use Symfony\Bundle\SecurityBundle\Security;
use App\DataTables\Column\IconLinkColumn;
use App\DataTables\Column\LocaleDateTimeColumn;
use App\DataTables\Column\LogEntryExtraColumn;
use App\DataTables\Column\LogEntryTargetColumn;
use App\DataTables\Column\RevertLogColumn;
use App\DataTables\Column\RowClassColumn;
use App\DataTables\Filters\LogFilter;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Contracts\TimeTravelInterface;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\CollectionElementDeleted;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementDeletedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\LogSystem\PartStockChangedLogEntry;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Exceptions\EntityNotSupportedException;
use App\Repository\LogEntryRepository;
use App\Services\ElementTypeNameGenerator;
use App\Services\EntityURLGenerator;
use App\Services\LogSystem\LogLevelHelper;
use App\Services\UserSystem\UserAvatarHelper;
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
use Symfony\Contracts\Translation\TranslatorInterface;

class LogDataTable implements DataTableTypeInterface
{
    protected LogEntryRepository $logRepo;

    public function __construct(protected ElementTypeNameGenerator $elementTypeNameGenerator, protected TranslatorInterface $translator,
        protected UrlGeneratorInterface $urlGenerator, protected EntityURLGenerator $entityURLGenerator, EntityManagerInterface $entityManager,
        protected Security $security, protected UserAvatarHelper $userAvatarHelper, protected LogLevelHelper $logLevelHelper)
    {
        $this->logRepo = $entityManager->getRepository(AbstractLogEntry::class);
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

        //This special $$rowClass column is used to set the row class depending on the log level. The class gets set by the frontend controller
        $dataTable->add('dont_matter', RowClassColumn::class, [
            'render' => fn($value, AbstractLogEntry $context) => $this->logLevelHelper->logLevelToTableColorClass($context->getLevelString()),
        ]);

        $dataTable->add('symbol', TextColumn::class, [
            'label' => '',
            'className' => 'no-colvis',
            'render' => fn($value, AbstractLogEntry $context): string => sprintf(
                '<i class="fas fa-fw %s" title="%s"></i>',
                $this->logLevelHelper->logLevelToIconClass($context->getLevelString()),
                $context->getLevelString()
            ),
        ]);

        $dataTable->add('id', TextColumn::class, [
            'label' => 'log.id',
            'visible' => false,
        ]);

        $dataTable->add('timestamp', LocaleDateTimeColumn::class, [
            'label' => 'log.timestamp',
            'timeFormat' => 'medium',
            'render' => fn(string $value, AbstractLogEntry $context): string => sprintf('<a href="%s">%s</a>',
                $this->urlGenerator->generate('log_details', ['id' => $context->getID()]),
                $value
            )
        ]);

        $dataTable->add('type', TextColumn::class, [
            'label' => 'log.type',
            'propertyPath' => 'type',
            'render' => function (string $value, AbstractLogEntry $context) {
                $text = $this->translator->trans('log.type.'.$value);

                if ($context instanceof PartStockChangedLogEntry) {
                    $text .= sprintf(
                        ' (<i>%s</i>)',
                        $this->translator->trans('log.part_stock_changed.' . $context->getInstockChangeType()->toExtraShortType())
                    );
                }

                return $text;
            },
        ]);

        $dataTable->add('level', TextColumn::class, [
            'label' => 'log.level',
            'visible' => 'system_log' === $options['mode'],
            'propertyPath' => 'levelString',
            'render' => fn(string $value, AbstractLogEntry $context) => $this->translator->trans('log.level.'.$value),
        ]);

        $dataTable->add('user', TextColumn::class, [
            'label' => 'log.user',
            'orderField' => 'user.name',
            'render' => function ($value, AbstractLogEntry $context): string {
                $user = $context->getUser();

                //If user was deleted, show the info from the username field
                if (!$user instanceof User) {
                    if ($context->isCLIEntry()) {
                        return sprintf('%s [%s]',
                            htmlentities($context->getCLIUsername()),
                            $this->translator->trans('log.cli_user')
                        );
                    }

                    //Else we just deal with a deleted user
                    return sprintf(
                        '@%s [%s]',
                        htmlentities($context->getUsername()),
                        $this->translator->trans('log.target_deleted'),
                    );
                }

                $img_url = $this->userAvatarHelper->getAvatarSmURL($user);

                return sprintf(
                    '<img src="%s" data-thumbnail="%s" class="avatar-xs" data-controller="elements--hoverpic"> <a href="%s">%s</a>',
                    $img_url,
                    $this->userAvatarHelper->getAvatarMdURL($user),
                    $this->urlGenerator->generate('user_info', ['id' => $user->getID()]),
                    htmlentities($user->getFullName(true))
                );
            },
        ]);

        $dataTable->add('target_type', EnumColumn::class, [
            'label' => 'log.target_type',
            'visible' => false,
            'class' => LogTargetType::class,
            'render' => function (LogTargetType $value, AbstractLogEntry $context) {
                $class = $value->toClass();
                if (null !== $class) {
                    return $this->elementTypeNameGenerator->getLocalizedTypeLabel($class);
                }

                return '';
            },
        ]);

        $dataTable->add('target', LogEntryTargetColumn::class, [
            'label' => 'log.target',
            'show_associated' => 'element_history' !== $options['mode'],
        ]);

        $dataTable->add('extra', LogEntryExtraColumn::class, [
            'label' => 'log.extra',
        ]);

        $dataTable->add('timeTravel', IconLinkColumn::class, [
            'label' => '',
            'icon' => 'fas fa-fw fa-eye',
            'href' => function ($value, AbstractLogEntry $context) {
                if (
                    ($context instanceof TimeTravelInterface
                        && $context->hasOldDataInformation())
                    || $context instanceof CollectionElementDeleted
                ) {
                    try {
                        $target = $this->logRepo->getTargetElement($context);
                        if ($target instanceof AbstractDBElement) {
                            return $this->entityURLGenerator->timeTravelURL($target, $context->getTimestamp());
                        }
                    } catch (EntityNotSupportedException) {
                        return null;
                    }
                }

                return null;
            },
            'disabled' => fn($value, AbstractLogEntry $context) => !$this->security->isGranted('show_history', $context->getTargetClass()),
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
                LogTargetType::USER,
                LogTargetType::GROUP,
            ]);
        }

        if (!empty($options['filter_elements'])) {
            foreach ($options['filter_elements'] as $element) {
                /** @var AbstractDBElement $element */

                $target_type = LogTargetType::fromElementClass($element);
                $target_id = $element->getID();

                $builder->orWhere('log.target_type = :filter_target_type AND log.target_id = :filter_target_id');
                $builder->setParameter('filter_target_type', $target_type);
                $builder->setParameter('filter_target_id', $target_id);
            }
        }
    }
}
