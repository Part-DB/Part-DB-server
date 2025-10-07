<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);

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

namespace App\Controller;

use App\Entity\Base\AbstractDBElement;
use App\Entity\LabelSystem\LabelOptions;
use App\Entity\LabelSystem\LabelProcessMode;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\Exceptions\TwigModeException;
use App\Form\LabelSystem\LabelDialogType;
use App\Repository\DBElementRepository;
use App\Services\ElementTypeNameGenerator;
use App\Services\LabelSystem\LabelGenerator;
use App\Services\Misc\RangeParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/label')]
class LabelController extends AbstractController
{
    public function __construct(protected LabelGenerator $labelGenerator, protected EntityManagerInterface $em, protected ElementTypeNameGenerator $elementTypeNameGenerator, protected RangeParser $rangeParser, protected TranslatorInterface $translator,
        private readonly ValidatorInterface $validator
    )
    {
    }

    #[Route(path: '/dialog', name: 'label_dialog')]
    #[Route(path: '/{profile}/dialog', name: 'label_dialog_profile')]
    public function generator(Request $request, ?LabelProfile $profile = null): Response
    {
        $this->denyAccessUnlessGranted('@labels.create_labels');

        //If we inherit a LabelProfile, the user need to have access to it...
        if ($profile instanceof LabelProfile) {
            $this->denyAccessUnlessGranted('read', $profile);
        }

        $label_options = $profile instanceof LabelProfile ? $profile->getOptions() : new LabelOptions();

        //We have to disable the options, if twig mode is selected and user is not allowed to use it.
        $disable_options = (LabelProcessMode::TWIG === $label_options->getProcessMode()) && !$this->isGranted('@labels.use_twig');

        $form = $this->createForm(LabelDialogType::class, null, [
            'disable_options' => $disable_options,
            'profile' => $profile
        ]);

        //Try to parse given target_type and target_id
        $target_type = $request->query->getEnum('target_type', LabelSupportedElement::class, null);
        $target_id = $request->query->get('target_id', null);
        $generate = $request->query->getBoolean('generate', false);

        if (!$profile instanceof LabelProfile && $target_type instanceof LabelSupportedElement) {
            $label_options->setSupportedElement($target_type);
        }
        if (is_string($target_id)) {
            $form['target_id']->setData($target_id);
        }

        $form['options']->setData($label_options);
        $form->handleRequest($request);

        /** @var LabelOptions $form_options */
        $form_options = $form['options']->getData();

        $pdf_data = null;
        $filename = 'invalid.pdf';

        if (($form->isSubmitted() && $form->isValid()) || ($generate && !$form->isSubmitted() && $profile instanceof LabelProfile)) {

            //Check if the label should be saved as profile
            if ($form->get('save_profile')->isClicked() && $this->isGranted('@labels.create_profiles')) { //@phpstan-ignore-line Phpstan does not recognize the isClicked method
                //Retrieve the profile name from the form
                $new_name = $form->get('save_profile_name')->getData();
                //ensure that the name is not empty
                if ($new_name === '' || $new_name === null) {
                    $form->get('save_profile_name')->addError(new FormError($this->translator->trans('label_generator.profile_name_empty')));
                    goto render;
                }

                $new_profile = new LabelProfile();
                $new_profile->setName($form->get('save_profile_name')->getData());
                $new_profile->setOptions($form_options);

                //Validate the profile name
                $errors = $this->validator->validate($new_profile);
                if (count($errors) > 0) {
                    foreach ($errors as $error) {
                        $form->get('save_profile_name')->addError(new FormError($error->getMessage()));
                    }
                    goto render;
                }

                $this->em->persist($new_profile);
                $this->em->flush();
                $this->addFlash('success', 'label_generator.profile_saved');

                return $this->redirectToRoute('label_dialog_profile', [
                    'profile' => $new_profile->getID(),
                    'target_id' => (string) $form->get('target_id')->getData()
                ]);
            }

            //Check if the current profile should be updated
            if ($form->has('update_profile')
                && $form->get('update_profile')->isClicked()  //@phpstan-ignore-line Phpstan does not recognize the isClicked method
                && $profile instanceof LabelProfile
                && $this->isGranted('edit', $profile)) {
                //Update the profile options
                $profile->setOptions($form_options);

                //Validate the profile name
                $errors = $this->validator->validate($profile);
                if (count($errors) > 0) {
                    foreach ($errors as $error) {
                        $this->addFlash('error', $error->getMessage());
                    }
                    goto render;
                }

                $this->em->persist($profile);
                $this->em->flush();
                $this->addFlash('success', 'label_generator.profile_updated');

                return $this->redirectToRoute('label_dialog_profile', [
                    'profile' => $profile->getID(),
                    'target_id' => (string) $form->get('target_id')->getData()
                ]);
            }

            $target_id = (string) $form->get('target_id')->getData();
            $targets = $this->findObjects($form_options->getSupportedElement(), $target_id);
            if ($targets !== []) {
                try {
                    $pdf_data = $this->labelGenerator->generateLabel($form_options, $targets);
                    $filename = $this->getLabelName($targets[0], $profile);
                } catch (TwigModeException $exception) {
                    $form->get('options')->get('lines')->addError(new FormError($exception->getSafeMessage()));
                }
            } else {
                //$this->addFlash('warning', 'label_generator.no_entities_found');
                $form->get('target_id')->addError(
                    new FormError($this->translator->trans('label_generator.no_entities_found'))
                );
            }

            //When the profile lines are empty, show a notice flash
            if (trim($form_options->getLines()) === '') {
                $this->addFlash('notice', 'label_generator.no_lines_given');
            }
        }

        render:
        return $this->render('label_system/dialog.html.twig', [
            'form' => $form,
            'pdf_data' => $pdf_data,
            'filename' => $filename,
            'profile' => $profile,
        ]);
    }

    protected function getLabelName(AbstractDBElement $element, ?LabelProfile $profile = null): string
    {
        $ret = 'label_'.$this->elementTypeNameGenerator->getLocalizedTypeLabel($element);
        $ret .= $element->getID();

        return $ret.'.pdf';
    }

    protected function findObjects(LabelSupportedElement $type, string $ids): array
    {
        $id_array = $this->rangeParser->parse($ids);

        /** @var DBElementRepository<AbstractDBElement> $repo */
        $repo = $this->em->getRepository($type->getEntityClass());

        return $repo->getElementsFromIDArray($id_array);
    }
}
