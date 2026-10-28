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

use App\Entity\AgentSystem\Agent;
use App\Entity\AgentSystem\AgentJob;
use App\Entity\AgentSystem\AgentJobStatus;
use App\Entity\Base\AbstractDBElement;
use App\Entity\LabelSystem\LabelOptions;
use App\Entity\LabelSystem\LabelProcessMode;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\Entity\UserSystem\User;
use App\Exceptions\TwigModeException;
use App\Form\LabelSystem\LabelDialogType;
use App\Repository\DBElementRepository;
use App\Services\ElementTypeNameGenerator;
use App\Services\LabelSystem\AgentJobCreator;
use App\Services\LabelSystem\LabelGenerator;
use App\Services\Misc\RangeParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

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

            //Check that we have read access to the targets
            foreach ($targets as $target) {
                $this->denyAccessUnlessGranted('read', $target);
            }

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

    /**
     * Connects this browser to a local print agent (stored in the browser only, see docs/usage/labels.md).
     */
    #[Route(path: '/agent', name: 'label_agent')]
    public function agent(): Response
    {
        $this->denyAccessUnlessGranted('@labels.create_labels');
        $user = $this->getUser();

        return $this->render('label_system/agent.html.twig', [
            'agents' => $user instanceof User ? $this->em->getRepository(Agent::class)->findBy(['owner' => $user], ['lastSeenAt' => 'DESC']) : [],
            'jobs' => $user instanceof User ? $this->em->getRepository(AgentJob::class)->createQueryBuilder('j')
                ->join('j.agent', 'a')->where('a.owner = :user')->setParameter('user', $user)
                ->orderBy('j.id', 'DESC')->setMaxResults(50)->getQuery()->getResult() : [],
        ]);
    }

    #[Route(path: '/agent/jobs/{id}/resend', name: 'label_agent_job_resend', methods: ['POST'])]
    public function agentJobResend(int $id, Request $request, AgentJobCreator $agentJobCreator): Response
    {
        $job = $this->ownAgentJob($id, $request);
        $agentJobCreator->resend($job);
        $this->addFlash('success', t('label_generator.agent.job_resent'));

        return $this->redirectToRoute('label_agent');
    }

    #[Route(path: '/agent/jobs/{id}/cancel', name: 'label_agent_job_cancel', methods: ['POST'])]
    public function agentJobCancel(int $id, Request $request): Response
    {
        $job = $this->ownAgentJob($id, $request);
        if ($job->getStatus() === AgentJobStatus::PENDING) {
            $job->setStatus(AgentJobStatus::CANCELLED);
            $this->em->flush();
        }

        return $this->redirectToRoute('label_agent');
    }

    #[Route(path: '/agent/agents/{id}/remove', name: 'label_agent_remove', methods: ['POST'])]
    public function agentRemove(int $id, Request $request): Response
    {
        $this->checkAgentPageRequest($request);
        $agent = $this->em->find(Agent::class, $id);
        if (!$agent instanceof Agent || $agent->getOwner() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }
        //Not relying on ON DELETE CASCADE: SQLite only enforces foreign keys if configured to
        $this->em->createQuery('DELETE FROM '.AgentJob::class.' j WHERE j.agent = :agent')->setParameter('agent', $agent)->execute();
        $this->em->remove($agent);
        $this->em->flush();

        return $this->redirectToRoute('label_agent');
    }

    private function checkAgentPageRequest(Request $request): void
    {
        $this->denyAccessUnlessGranted('@labels.create_labels');
        if (!$this->isCsrfTokenValid('label_agent', $request->request->getString('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token');
        }
    }

    /**
     * A job of one of the current user's agents; other users' jobs are reported as missing.
     */
    private function ownAgentJob(int $id, Request $request): AgentJob
    {
        $this->checkAgentPageRequest($request);
        $job = $this->em->find(AgentJob::class, $id);
        if (!$job instanceof AgentJob || $job->getAgent()?->getOwner() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }
        return $job;
    }

    /**
     * Sends a print job to one of the current user's print agents.
     */
    #[Route(path: '/print', name: 'label_print', methods: ['POST'])]
    public function print(Request $request, AgentJobCreator $agentJobCreator): Response
    {
        $this->denyAccessUnlessGranted('@labels.create_labels');
        if (!$this->isCsrfTokenValid('label_print', $request->request->getString('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token');
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $profile = $this->em->find(LabelProfile::class, $request->request->getInt('profile'));
        if (!$profile instanceof LabelProfile) {
            throw $this->createNotFoundException();
        }
        $this->denyAccessUnlessGranted('read', $profile);

        [$agentId, $printer] = explode('|', $request->request->getString('printer'), 2) + [1 => ''];
        $agent = $this->em->find(Agent::class, (int) $agentId);
        //Other users' agents are reported as missing, so their ids don't leak
        if (!$agent instanceof Agent || $agent->getOwner() !== $user || !$agent->hasPrinter($printer)) {
            throw $this->createNotFoundException();
        }

        $type = LabelSupportedElement::tryFrom($request->request->getString('target_type')) ?? $profile->getOptions()->getSupportedElement();
        try {
            $job = $agentJobCreator->create($user, $agent, $printer, $profile, $type, $request->request->getString('target_id'));
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $params = [
            '%count%' => $agentJobCreator->countLabels($job->getPayload()['element_ids']),
            '%printer%' => $agent->getPrinterName($printer),
        ];
        if ($agent->isOnline()) {
            $this->addFlash('success', t('label_generator.print.sent', $params));
        } else {
            $this->addFlash('warning', t('label_generator.print.sent_offline', $params));
        }

        //Only redirect to local paths ("//host" and "/\host" would leave Part-DB)
        $redirect = $request->request->getString('_redirect');
        if (preg_match('#^/(?![/\\\\])#', $redirect) === 1) {
            return $this->redirect($redirect);
        }
        return $this->redirectToRoute('label_dialog_profile', ['profile' => $profile->getID(), 'target_id' => $job->getPayload()['element_ids']]);
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
