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

namespace App\Services\AdminPages\Extension;

use App\Entity\LabelSystem\LabelProcessMode;
use App\Entity\LabelSystem\LabelProfile;
use App\Exceptions\TwigModeException;
use App\Services\AdminPages\AbstractAdminPageExtension;
use App\Services\LabelSystem\LabelExampleElementsGenerator;
use App\Services\LabelSystem\LabelGenerator;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class LabelProfileAdminExtension extends AbstractAdminPageExtension
{
    public function __construct(
        private readonly LabelExampleElementsGenerator $exampleGenerator,
        private readonly LabelGenerator $labelGenerator,
        private readonly AuthorizationCheckerInterface $authChecker,
    ) {
    }

    public function formOptions(object $entity): array
    {
        if (!$entity instanceof LabelProfile) {
            throw new \InvalidArgumentException('Expected ' . LabelProfile::class . ', got ' . $entity::class . '.');
        }

        if (LabelProcessMode::TWIG === $entity->getOptions()->getProcessMode()
            && !$this->authChecker->isGranted('@labels.use_twig')) {
            return ['disable_options' => true];
        }

        return [];
    }

    public function additionalTemplateVars(object $entity, FormInterface $form): array
    {
        if (!$entity instanceof LabelProfile) {
            throw new \InvalidArgumentException('Expected ' . LabelProfile::class . ', got ' . $entity::class . '.');
        }

        // No preview for a new, unsaved profile with no content yet — nothing meaningful to render.
        if ($entity->getID() === null && $entity->getOptions()->getLines() === '') {
            return ['pdf_data' => null];
        }

        $example = $this->exampleGenerator->getElement($entity->getOptions()->getSupportedElement());
        $pdfData = null;
        try {
            $pdfData = $this->labelGenerator->generateLabel($entity->getOptions(), $example);
        } catch (TwigModeException $e) {
            $form->get('options')->get('lines')->addError(new FormError($e->getSafeMessage()));
        }

        return ['pdf_data' => $pdfData];
    }
}
