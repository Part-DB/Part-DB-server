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

use App\Entity\Parts\Part;
use App\Form\Type\PartLotSelectType;
use App\Helpers\Projects\BuildPlannedProjectRequest;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Confirmation form for building an already-planned project. Much simpler than ProjectBuildType, as the
 * amounts to withdraw are already fixed by the plan's reservations - only a comment and (if applicable) the
 * builds-part lot need to be collected.
 */
class BuildPlannedProjectType extends AbstractType
{
    public function __construct(private readonly Security $security)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BuildPlannedProjectRequest::class,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('submit', SubmitType::class, [
            'label' => 'planned_build.build.btn_build',
            'disabled' => !$this->security->isGranted('@parts_stock.withdraw'),
        ]);

        $builder->add('comment', TextareaType::class, [
            'label' => 'part.info.withdraw_modal.comment',
            'empty_data' => '',
            'required' => false,
        ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event) {
            $form = $event->getForm();
            /** @var BuildPlannedProjectRequest $build_request */
            $build_request = $event->getData();
            $project = $build_request->getPlannedProject()->getProject();
            $has_builds_part = $project?->getBuildPart() instanceof Part;

            $form->add('addBuildsToBuildsPart', CheckboxType::class, [
                'label' => 'project.build.add_builds_to_builds_part',
                'required' => false,
                'disabled' => !$has_builds_part,
            ]);

            if ($has_builds_part) {
                $form->add('buildsPartLot', PartLotSelectType::class, [
                    'label' => 'project.build.builds_part_lot',
                    'required' => false,
                    'part' => $project->getBuildPart(),
                    'placeholder' => 'project.build.buildsPartLot.new_lot',
                ]);
            }
        });
    }
}
