<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\UserSystem\SavedFilter;
use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Saves the filters of the part lists under a name and opens, renames, shares and deletes them.
 */
#[Route(path: '/saved_filters')]
class SavedFilterController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    #[Route(path: '', name: 'saved_filter_list', methods: ['GET'])]
    public function list(): Response
    {
        $this->denyAccessUnlessGranted('@parts.read');

        $user = $this->getUser();
        $filters = $this->entityManager->getRepository(SavedFilter::class)
            ->findVisibleFor($user instanceof User ? $user : null);

        return $this->render('parts/saved_filters/list.html.twig', [
            'filters' => $filters,
        ]);
    }

    /**
     * Saves the filter of the part list, from which the form was sent. The part list sends its route and the
     * query parameters of its applied filter.
     */
    #[Route(path: '/save', name: 'saved_filter_save', methods: ['POST'])]
    public function save(Request $request, ValidatorInterface $validator): Response
    {
        $this->denyAccessUnlessGranted('create', SavedFilter::class);

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Only logged-in users can save filters!');
        }

        $route = (string) $request->request->get('route', '');
        $routeParams = json_decode((string) $request->request->get('route_params', '{}'), true);
        parse_str((string) $request->request->get('query', ''), $query);

        if (!in_array($route, SavedFilter::ALLOWED_ROUTES, true) || !is_array($routeParams)) {
            $this->addFlash('error', 'saved_filter.save.invalid_page');
            return $this->redirectToRoute('parts_show_all');
        }

        $filter = (new SavedFilter())
            ->setOwner($user)
            ->setName(trim((string) $request->request->get('name', '')))
            ->setShared($request->request->getBoolean('shared') && $this->isGranted('share', SavedFilter::class))
            ->setTarget($route, $routeParams, $query, $this->getUserTimezone());

        if (!$this->isCsrfTokenValid('saved_filter_save', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'csfr_invalid');
            return $this->redirectToFilter($filter);
        }

        if ($filter->getQuery() === []) {
            $this->addFlash('error', 'saved_filter.save.no_filter');
            return $this->redirectToFilter($filter);
        }

        $errors = $validator->validate($filter);
        if (count($errors) > 0) {
            $this->addFlash('error', 'saved_filter.save.no_name');
            return $this->redirectToFilter($filter);
        }

        $this->entityManager->persist($filter);
        $this->entityManager->flush();
        $this->invalidateTrees();

        $this->addFlash('success', 'saved_filter.save.success');

        return $this->redirectToFilter($filter);
    }

    #[Route(path: '/{id}', name: 'saved_filter_open', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function open(SavedFilter $filter): Response
    {
        $this->denyAccessUnlessGranted('read', $filter);

        return $this->redirectToFilter($filter);
    }

    #[Route(path: '/{id}/edit', name: 'saved_filter_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(SavedFilter $filter, Request $request): Response
    {
        $this->denyAccessUnlessGranted('edit', $filter);

        $builder = $this->createFormBuilder($filter)
            ->add('name', TextType::class, [
                'label' => 'saved_filter.name',
            ]);

        if ($this->isGranted('share', $filter)) {
            $builder->add('shared', CheckboxType::class, [
                'label' => 'saved_filter.shared',
                'help' => 'saved_filter.shared.help',
                'required' => false,
            ]);
        }

        $form = $builder
            ->add('submit', SubmitType::class, [
                'label' => 'save',
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->invalidateTrees();
            $this->addFlash('success', 'saved_filter.edit.success');

            return $this->redirectToRoute('saved_filter_list');
        }

        return $this->render('parts/saved_filters/edit.html.twig', [
            'filter' => $filter,
            'form' => $form,
        ]);
    }

    #[Route(path: '/{id}/delete', name: 'saved_filter_delete', requirements: ['id' => '\d+'], methods: ['DELETE', 'POST'])]
    public function delete(SavedFilter $filter, Request $request): Response
    {
        $this->denyAccessUnlessGranted('delete', $filter);

        if (!$this->isCsrfTokenValid('delete_saved_filter'.$filter->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'csfr_invalid');
            return $this->redirectToRoute('saved_filter_list');
        }

        $this->entityManager->remove($filter);
        $this->entityManager->flush();
        $this->invalidateTrees();

        $this->addFlash('success', 'saved_filter.delete.success');

        return $this->redirectToRoute('saved_filter_list');
    }

    /**
     * Redirects to the part list of the given filter with the filter applied.
     */
    private function redirectToFilter(SavedFilter $filter): RedirectResponse
    {
        try {
            //Parameters, which are not part of the route, are added as query parameters
            return $this->redirect($this->generateUrl($filter->getRoute(),
                $filter->getRouteParams() + $filter->getQuery($this->getUserTimezone())));
        } catch (RoutingException) {
            $this->addFlash('error', 'saved_filter.open.invalid');
            return $this->redirectToRoute('saved_filter_list');
        }
    }

    /**
     * Returns the timezone, in which the filter forms read the date-times. It is set to the timezone of the current
     * user by the SetUserTimezoneSubscriber.
     */
    private function getUserTimezone(): \DateTimeZone
    {
        return new \DateTimeZone(date_default_timezone_get());
    }

    /**
     * The saved filters are shown in the tools tree of the sidebar, so the cached trees have to be renewed
     */
    private function invalidateTrees(): void
    {
        $this->cache->invalidateTags(['tree_tools', 'sidebar_tree_update']);
    }
}
