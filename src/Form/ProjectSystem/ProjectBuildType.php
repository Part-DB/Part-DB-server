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

use App\Form\Type\SIUnitType;
use App\Helpers\Projects\ProjectBuildRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjectBuildType extends AbstractType implements DataMapperInterface
{
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

        $builder->add('submit', SubmitType::class, []);

        //The form is initially empty, we have to define the fields after we know the data
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event) {
            $form = $event->getForm();
            /** @var ProjectBuildRequest $build_request */
            $build_request = $event->getData();

            foreach ($build_request->getPartBomEntries() as $bomEntry) {
                //Every part lot has a field to specify the number of parts to take from this lot
                foreach ($build_request->getPartLotsForBOMEntry($bomEntry) as $lot) {
                    $form->add('lot_' . $lot->getID(), SIUnitType::class, [
                        'label' => false,
                        'measurement_unit' => $bomEntry->getPart()->getPartUnit(),
                    ]);
                }
            }

        });
    }

    public function mapDataToForms($viewData, \Traversable $forms)
    {
        $forms = iterator_to_array($forms);

        dump($viewData);
        dump ($forms);
    }

    public function mapFormsToData(\Traversable $forms, &$viewData)
    {
        // TODO: Implement mapFormsToData() method.
    }
}