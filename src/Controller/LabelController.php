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

namespace App\Controller;


use App\Entity\Base\AbstractDBElement;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\LabelSystem\LabelOptions;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parts\Part;
use App\Form\LabelOptionsType;
use App\Form\LabelSystem\LabelDialogType;
use App\Helpers\LabelResponse;
use App\Services\ElementTypeNameGenerator;
use App\Services\LabelSystem\LabelGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/label")
 * @package App\Controller
 */
class LabelController extends AbstractController
{
    protected $labelGenerator;
    protected $em;
    protected $elementTypeNameGenerator;

    public function __construct(LabelGenerator $labelGenerator, EntityManagerInterface $em, ElementTypeNameGenerator $elementTypeNameGenerator)
    {
        $this->labelGenerator = $labelGenerator;
        $this->em = $em;
        $this->elementTypeNameGenerator = $elementTypeNameGenerator;
    }

    /**
     * @Route("/{profile}/{part}/view")
     */
    public function view(LabelProfile $profile, Part $part)
    {
        $label = $this->labelGenerator->generateLabel($profile->getOptions(), $part);

        $response = new LabelResponse($label);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'label.pdf');

        return $response;
    }

    /**
     * @Route("/dialog", name="label_dialog")
     * @Route("/{profile}/dialog")
     */
    public function generator(Request $request, ?LabelProfile $profile = null)
    {
        if ($profile) {
            $label_options = $profile->getOptions();
        } else {
            $label_options = new LabelOptions();
        }

        $form = $this->createForm(LabelDialogType::class);

        //Try to parse given target_type and target_id
        $target_type = $request->query->get('target_type', null);
        $target_id = $request->query->get('target_id', null);
        if ($profile === null && is_string($target_type)) {
            $label_options->setSupportedElement($target_type);
        }
        if (is_numeric($target_id)) {
            $form['target_id']->setData((int) $target_id);
        }


        $form['options']->setData($label_options);
        $form->handleRequest($request);

        /** @var LabelOptions $form_options */
        $form_options = $form['options']->getData();

        $pdf_data = null;
        $filename = 'invalid.pdf';

        if ($form->isSubmitted() && $form->isValid()) {
            $target_id = (int) $form->get('target_id')->getData();
            $target = $this->findObject($form_options->getSupportedElement(), $target_id);
            $pdf_data = $this->labelGenerator->generateLabel($form_options, $target);
            $filename = $this->getLabelName($target, $profile);
        }

        return $this->render('LabelSystem/dialog.html.twig', [
            'form' => $form->createView(),
            'pdf_data' => $pdf_data,
            'filename' => $filename,
        ]);
    }

    protected function getLabelName(AbstractDBElement $element, ?LabelProfile $profile = null): string
    {
        $ret = 'label_' . $this->elementTypeNameGenerator->getLocalizedTypeLabel($element);
        $ret .= $element->getID();

        return $ret . '.pdf';
    }

    protected function findObject(string $type, int $id): object
    {
        if(!isset(LabelGenerator::CLASS_SUPPORT_MAPPING[$type])) {
            throw new \InvalidArgumentException('The given type is not known and can not be mapped to a class!');
        }
        return $this->em->find(LabelGenerator::CLASS_SUPPORT_MAPPING[$type], $id);
    }
}