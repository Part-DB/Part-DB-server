<?php

declare(strict_types=1);

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
namespace App\Form\ProjectSystem;

use App\Form\Type\SIUnitType;
use App\Helpers\Projects\PlanProjectRequest;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form used to review/adjust and confirm a PlanProjectRequest before it is turned into a PlannedProject.
 * Mirrors ProjectBuildType's dynamic per-lot DataMapperInterface structure, but reserves stock (never touches
 * amounts) and additionally collects a name and comment for the resulting PlannedProject.
 */
class PlanProjectType extends AbstractType implements DataMapperInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => PlanProjectRequest::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);

        $builder->add('submit', SubmitType::class, [
            'label' => 'planned_build.plan.btn_plan',
            'disabled' => !$this->security->isGranted('@parts_stock.reserve'),
        ]);

        $builder->add('name', TextType::class, [
            'label' => 'planned_build.plan.name',
            'empty_data' => '',
        ]);

        $builder->add('comment', TextareaType::class, [
            'label' => 'planned_build.plan.comment',
            'help' => 'planned_build.plan.comment.hint',
            'empty_data' => '',
            'required' => false,
        ]);

        //The form is initially empty, we have to define the per-lot fields after we know the data
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event) {
            $form = $event->getForm();
            /** @var PlanProjectRequest $plan_request */
            $plan_request = $event->getData();

            foreach ($plan_request->getPartBomEntries() as $bomEntry) {
                //Every part lot has a field to specify the number of parts to reserve from this lot
                foreach ($plan_request->getPartLotsForBOMEntry($bomEntry) as $lot) {
                    $form->add('lot_' . $lot->getID(), SIUnitType::class, [
                        'label' => false,
                        'measurement_unit' => $bomEntry->getPart()->getPartUnit(),
                        'max' => min($plan_request->getNeededAmountForBOMEntry($bomEntry), $lot->getAvailableAmount()),
                        'disabled' => !$this->security->isGranted('reserve', $lot),
                    ]);
                }
            }
        });
    }

    public function mapDataToForms($data, \Traversable $forms): void
    {
        if (!$data instanceof PlanProjectRequest) {
            throw new \RuntimeException('Data must be an instance of ' . PlanProjectRequest::class);
        }

        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);
        foreach ($forms as $key => $form) {
            $matches = [];
            if (preg_match('/^lot_(\d+)$/', $key, $matches)) {
                $lot_id = (int) $matches[1];
                $form->setData($data->getLotReservationAmount($lot_id));
            }
        }

        $forms['name']->setData($data->getName());
        $forms['comment']->setData($data->getComment());
    }

    public function mapFormsToData(\Traversable $forms, &$data): void
    {
        if (!$data instanceof PlanProjectRequest) {
            throw new \RuntimeException('Data must be an instance of ' . PlanProjectRequest::class);
        }

        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);

        foreach ($forms as $key => $form) {
            $matches = [];
            if (preg_match('/^lot_(\d+)$/', $key, $matches)) {
                $lot_id = (int) $matches[1];
                $data->setLotReservationAmount($lot_id, (float) $form->getData());
            }
        }

        $data->setName($forms['name']->getData());
        $data->setComment($forms['comment']->getData());
    }
}
