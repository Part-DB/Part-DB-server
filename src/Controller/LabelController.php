<?php

declare(strict_types=1);

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

namespace App\Controller;

use App\Entity\Base\AbstractDBElement;
use App\Entity\LabelSystem\LabelOptions;
use App\Entity\LabelSystem\LabelProfile;
use App\Exceptions\TwigModeException;
use App\Form\LabelSystem\LabelDialogType;
use App\Repository\DBElementRepository;
use App\Services\ElementTypeNameGenerator;
use App\Services\LabelSystem\LabelGenerator;
use App\Services\Misc\RangeParser;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/label")
 */
class LabelController extends AbstractController
{
    protected $labelGenerator;
    protected $em;
    protected $elementTypeNameGenerator;
    protected $rangeParser;
    protected $translator;

    public function __construct(LabelGenerator $labelGenerator, EntityManagerInterface $em, ElementTypeNameGenerator $elementTypeNameGenerator,
        RangeParser $rangeParser, TranslatorInterface $translator)
    {
        $this->labelGenerator = $labelGenerator;
        $this->em = $em;
        $this->elementTypeNameGenerator = $elementTypeNameGenerator;
        $this->rangeParser = $rangeParser;
        $this->translator = $translator;
    }

    /**
     * @Route("/dialog", name="label_dialog")
     * @Route("/{profile}/dialog", name="label_dialog_profile")
     */
    public function generator(Request $request, ?LabelProfile $profile = null): Response
    {
        $this->denyAccessUnlessGranted('@labels.create_labels');

        //If we inherit a LabelProfile, the user need to have access to it...
        if (null !== $profile) {
            $this->denyAccessUnlessGranted('read', $profile);
        }

        if ($profile) {
            $label_options = $profile->getOptions();
        } else {
            $label_options = new LabelOptions();
        }

        //We have to disable the options, if twig mode is selected and user is not allowed to use it.
        $disable_options = 'twig' === $label_options->getLinesMode() && !$this->isGranted('@labels.use_twig');

        $form = $this->createForm(LabelDialogType::class, null, [
            'disable_options' => $disable_options,
        ]);

        //Try to parse given target_type and target_id
        $target_type = $request->query->get('target_type', null);
        $target_id = $request->query->get('target_id', null);
        $generate = $request->query->getBoolean('generate', false);

        if (null === $profile && is_string($target_type)) {
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

        //Generate PDF either when the form is submitted and valid, or the form  was not submit yet, and generate is set
        if (($form->isSubmitted() && $form->isValid()) || ($generate && !$form->isSubmitted() && null !== $profile)) {
            $target_id = (string) $form->get('target_id')->getData();
            $targets = $this->findObjects($form_options->getSupportedElement(), $target_id);
            if (!empty($targets)) {
                try {
                    $pdf_data = $this->labelGenerator->generateLabel($form_options, $targets);
                    $filename = $this->getLabelName($targets[0], $profile);
                } catch (TwigModeException $exception) {
                    $form->get('options')->get('lines')->addError(new FormError($exception->getMessage()));
                }
            } else {
                //$this->addFlash('warning', 'label_generator.no_entities_found');
                $form->get('target_id')->addError(
                    new FormError($this->translator->trans('label_generator.no_entities_found'))
                );
            }
        }

        return $this->renderForm('LabelSystem/dialog.html.twig', [
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

    protected function findObjects(string $type, string $ids): array
    {
        if (!isset(LabelGenerator::CLASS_SUPPORT_MAPPING[$type])) {
            throw new InvalidArgumentException('The given type is not known and can not be mapped to a class!');
        }

        $id_array = $this->rangeParser->parse($ids);

        /** @var DBElementRepository $repo */
        $repo = $this->em->getRepository(LabelGenerator::CLASS_SUPPORT_MAPPING[$type]);

        return $repo->getElementsFromIDArray($id_array);
    }
}
