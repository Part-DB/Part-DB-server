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

declare(strict_types=1);


namespace App\Services\Parts;

use App\Entity\Parts\Part;
use App\Exceptions\AttachmentDownloadException;
use App\Form\Part\PartBaseType;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\LogSystem\EventCommentHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PartFormHelper
{
    private function __construct(private readonly TranslatorInterface $translator, private readonly EventCommentHelper $commentHelper,
        private readonly AttachmentSubmitHandler $attachmentSubmitHandler, private readonly EntityManagerInterface $em,
        private readonly FormFactoryInterface $formFactory)
    {

    }

    public function renderCreateForm(Request $request, Part $new_part = null, array $form_options = []): Response
    {
        $form = $this->formFactory->create(PartBaseType::class, $new_part, $form_options);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            //Upload passed files
            $attachments = $form['attachments'];
            foreach ($attachments as $attachment) {
                /** @var FormInterface $attachment */
                $options = [
                    'secure_attachment' => $attachment['secureFile']->getData(),
                    'download_url' => $attachment['downloadURL']->getData(),
                ];

                try {
                    $this->attachmentSubmitHandler->handleFormSubmit($attachment->getData(), $attachment['file']->getData(), $options);
                } catch (AttachmentDownloadException $attachmentDownloadException) {
                    $this->addFlash(
                        'error',
                        $this->translator->trans('attachment.download_failed').' '.$attachmentDownloadException->getMessage()
                    );
                }
            }

            $this->commentHelper->setMessage($form['log_comment']->getData());

            $this->em->persist($new_part);
            $this->em->flush();
            $this->addFlash('success', 'part.created_flash');

            //If a redirect URL was given, redirect there
            if ($request->query->get('_redirect')) {
                return $this->redirect($request->query->get('_redirect'));
            }

            //Redirect to clone page if user wished that...
            //@phpstan-ignore-next-line
            if ('save_and_clone' === $form->getClickedButton()->getName()) {
                return $this->redirectToRoute('part_clone', ['id' => $new_part->getID()]);
            }
            //@phpstan-ignore-next-line
            if ('save_and_new' === $form->getClickedButton()->getName()) {
                return $this->redirectToRoute('part_new');
            }

            return $this->redirectToRoute('part_edit', ['id' => $new_part->getID()]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'part.created_flash.invalid');
        }

        return $this->render('parts/edit/new_part.html.twig',
            [
                'part' => $new_part,
                'form' => $form,
            ]);
    }
}