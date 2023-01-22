<?php
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

use App\Entity\Parts\PartLot;
use App\Form\Type\PartLotSelectType;
use App\Form\Type\SIUnitType;
use App\Helpers\Projects\ProjectBuildRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class ProjectBuildType extends AbstractType implements DataMapperInterface
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'compound' => true,
            'data_class' => ProjectBuildRequest::class
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setDataMapper($this);

        $builder->add('submit', SubmitType::class, [
            'label' => 'project.build.btn_build',
            'disabled' => !$this->security->isGranted('@parts_stock.withdraw'),
        ]);

        $builder->add('comment', TextType::class, [
            'label' => 'part.info.withdraw_modal.comment',
            'help' => 'part.info.withdraw_modal.comment.hint',
            'empty_data' => '',
            'required' => false,
        ]);


        //The form is initially empty, we have to define the fields after we know the data
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event) {
            $form = $event->getForm();
            /** @var ProjectBuildRequest $build_request */
            $build_request = $event->getData();

            $form->add('addBuildsToBuildsPart', CheckboxType::class, [
                'label' => 'project.build.add_builds_to_builds_part',
                'required' => false,
                'disabled' => $build_request->getProject()->getBuildPart() === null,
            ]);

            if ($build_request->getProject()->getBuildPart()) {
                $form->add('buildsPartLot', PartLotSelectType::class, [
                    'label' => 'project.build.builds_part_lot',
                    'required' => false,
                    'part' => $build_request->getProject()->getBuildPart(),
                    'placeholder' => 'project.build.buildsPartLot.new_lot'
                ]);
            }

            foreach ($build_request->getPartBomEntries() as $bomEntry) {
                //Every part lot has a field to specify the number of parts to take from this lot
                foreach ($build_request->getPartLotsForBOMEntry($bomEntry) as $lot) {
                    $form->add('lot_' . $lot->getID(), SIUnitType::class, [
                        'label' => false,
                        'measurement_unit' => $bomEntry->getPart()->getPartUnit(),
                        'max' => min($build_request->getNeededAmountForBOMEntry($bomEntry), $lot->getAmount()),
                        'disabled' => !$this->security->isGranted('withdraw', $lot),
                    ]);
                }
            }

        });
    }

    public function mapDataToForms($data, \Traversable $forms)
    {
        if (!$data instanceof ProjectBuildRequest) {
            throw new \RuntimeException('Data must be an instance of ' . ProjectBuildRequest::class);
        }

        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);
        foreach ($forms as $key => $form) {
            //Extract the lot id from the form name
            $matches = [];
            if (preg_match('/^lot_(\d+)$/', $key, $matches)) {
                $lot_id = (int) $matches[1];
                $form->setData($data->getLotWithdrawAmount($lot_id));
            }
        }

        $forms['comment']->setData($data->getComment());
        $forms['addBuildsToBuildsPart']->setData($data->getAddBuildsToBuildsPart());
        if (isset($forms['buildsPartLot'])) {
            $forms['buildsPartLot']->setData($data->getBuildsPartLot());
        }

    }

    public function mapFormsToData(\Traversable $forms, &$data)
    {
        if (!$data instanceof ProjectBuildRequest) {
            throw new \RuntimeException('Data must be an instance of ' . ProjectBuildRequest::class);
        }

        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);

        foreach ($forms as $key => $form) {
            //Extract the lot id from the form name
            $matches = [];
            if (preg_match('/^lot_(\d+)$/', $key, $matches)) {
                $lot_id = (int) $matches[1];
                $data->setLotWithdrawAmount($lot_id, $form->getData());
            }
        }

        $data->setComment($forms['comment']->getData());
        if (isset($forms['buildsPartLot'])) {
            $lot = $forms['buildsPartLot']->getData();
            if (!$lot) { //When the user selected "Create new lot", create a new lot
                $lot = new PartLot();
                $description = 'Build ' . date('Y-m-d H:i:s');
                if (!empty($data->getComment())) {
                    $description .= ' (' . $data->getComment() . ')';
                }
                $lot->setDescription($description);

                $data->getProject()->getBuildPart()->addPartLot($lot);
            }

            $data->setBuildsPartLot($lot);
        }
        //This has to be set after the builds part lot, so that it can disable the option
        $data->setAddBuildsToBuildsPart($forms['addBuildsToBuildsPart']->getData());
    }
}