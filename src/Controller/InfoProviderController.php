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


namespace App\Controller;

use App\Exceptions\AttachmentDownloadException;
use App\Form\InfoProviderSystem\PartSearchType;
use App\Form\Part\PartBaseType;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use App\Services\InfoProviderSystem\ProviderRegistry;
use App\Services\LogSystem\EventCommentHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/tools/info_providers')]
class InfoProviderController extends  AbstractController
{

    public function __construct(private readonly ProviderRegistry $providerRegistry,
        private readonly PartInfoRetriever $infoRetriever, private readonly EventCommentHelper $commentHelper)
    {

    }

    #[Route('/providers', name: 'info_providers_list')]
    public function listProviders(): Response
    {
        return $this->render('info_providers/providers_list/providers_list.html.twig', [
            'active_providers' => $this->providerRegistry->getActiveProviders(),
            'disabled_providers' => $this->providerRegistry->getDisabledProviders(),
        ]);
    }

    #[Route('/search', name: 'info_providers_search')]
    public function search(Request $request): Response
    {
        $form = $this->createForm(PartSearchType::class);
        $form->handleRequest($request);

        $results = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $keyword = $form->get('keyword')->getData();
            $providers = $form->get('providers')->getData();

            $results = $this->infoRetriever->searchByKeyword(keyword: $keyword, providers: $providers);
        }

        return $this->render('info_providers/search/part_search.html.twig', [
            'form' => $form,
            'results' => $results,
        ]);
    }

    #[Route('/part/{providerKey}/{providerId}/create', name: 'info_providers_create_part')]
    public function createPart(Request $request, EntityManagerInterface $em, TranslatorInterface $translator,
        AttachmentSubmitHandler $attachmentSubmitHandler, string $providerKey, string $providerId): Response
    {

        $new_part = $this->infoRetriever->createPart($providerKey, $providerId);

        $form = $this->createForm(PartBaseType::class, $new_part);

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
                    $attachmentSubmitHandler->handleFormSubmit($attachment->getData(), $attachment['file']->getData(), $options);
                } catch (AttachmentDownloadException $attachmentDownloadException) {
                    $this->addFlash(
                        'error',
                        $translator->trans('attachment.download_failed').' '.$attachmentDownloadException->getMessage()
                    );
                }
            }

            $this->commentHelper->setMessage($form['log_comment']->getData());

            $em->persist($new_part);
            $em->flush();
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