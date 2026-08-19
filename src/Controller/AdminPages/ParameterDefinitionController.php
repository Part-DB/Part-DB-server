<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace App\Controller\AdminPages;

use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parameters\ParameterDefinition;
use App\Form\AdminPages\ParameterDefinitionAdminForm;
use App\Repository\ParameterRepository;
use App\Services\ImportExportSystem\EntityExporter;
use App\Services\ImportExportSystem\EntityImporter;
use App\Services\Trees\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/parameter_definition')]
final class ParameterDefinitionController extends BaseAdminController
{
    protected string $entity_class = ParameterDefinition::class;
    protected string $twig_template = 'admin/parameter_definition_admin.html.twig';
    protected string $form_class = ParameterDefinitionAdminForm::class;
    protected string $route_base = 'parameter_definition';
    protected string $attachment_class = '';
    protected ?string $parameter_class = null;

    #[Route(path: '/{id}', name: 'parameter_definition_delete', methods: ['DELETE'])]
    public function delete(Request $request, ParameterDefinition $entity, StructuralElementRecursionHelper $recursionHelper): RedirectResponse
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    #[Route(path: '/{id}/edit/{timestamp}', name: 'parameter_definition_edit', requirements: ['id' => '\d+'])]
    #[Route(path: '/{id}', requirements: ['id' => '\d+'])]
    public function edit(ParameterDefinition $entity, Request $request, EntityManagerInterface $em, ?string $timestamp = null): Response
    {
        return $this->_edit($entity, $request, $em, $timestamp);
    }

    #[Route(path: '/new', name: 'parameter_definition_new')]
    #[Route(path: '/{id}/clone', name: 'parameter_definition_clone')]
    #[Route(path: '/')]
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer, ?ParameterDefinition $entity = null): Response
    {
        return $this->_new($request, $em, $importer, $entity);
    }

    #[Route(path: '/export', name: 'parameter_definition_export_all')]
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportAll($em, $exporter, $request);
    }

    #[Route(path: '/{id}/export', name: 'parameter_definition_export')]
    public function exportEntity(ParameterDefinition $entity, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }

    protected function deleteCheck(AbstractNamedDBElement $entity): bool
    {
        if ($entity instanceof ParameterDefinition) {
            $repository = $this->entityManager->getRepository(AbstractParameter::class);
            if (!$repository instanceof ParameterRepository) {
                throw new \LogicException('The abstract parameter repository is not configured correctly.');
            }

            if ($repository->countByDefinition($entity) > 0) {
                $this->addFlash('error', 'parameter_definition.delete.in_use');

                return false;
            }
        }

        return parent::deleteCheck($entity);
    }
}
