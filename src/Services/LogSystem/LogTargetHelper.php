<?php

declare(strict_types=1);

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
namespace App\Services\LogSystem;

use App\Entity\Base\AbstractDBElement;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\UserNotAllowedLogEntry;
use App\Repository\LogEntryRepository;
use App\Services\ElementTypeNameGenerator;
use App\Services\EntityURLGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class LogTargetHelper
{
    protected LogEntryRepository $entryRepository;

    public function __construct(protected EntityManagerInterface $em, protected EntityURLGenerator $entityURLGenerator,
        protected ElementTypeNameGenerator $elementTypeNameGenerator, protected TranslatorInterface $translator)
    {
        $this->entryRepository = $em->getRepository(AbstractLogEntry::class);
    }

    private function configureOptions(OptionsResolver $resolver): self
    {
        $resolver->setDefault('show_associated', true);
        $resolver->setDefault('showAccessDeniedPath', true);

        return $this;
    }

    public function formatTarget(AbstractLogEntry $context, array $options = []): string
    {
        $optionsResolver = new OptionsResolver();
        $this->configureOptions($optionsResolver);
        $options = $optionsResolver->resolve($options);

        if ($context instanceof UserNotAllowedLogEntry && $options['showAccessDeniedPath']) {
            return htmlspecialchars($context->getPath());
        }

        /** @var AbstractLogEntry $context */
        $target = $this->entryRepository->getTargetElement($context);

        //If the target is null and the context has a target, that means that the target was deleted. Show it that way.
        if (!$target instanceof AbstractDBElement) {
            if ($context->hasTarget()) {
                return $this->elementTypeNameGenerator->formatElementDeletedHTML($context->getTargetClass(),
                    $context->getTargetID());
            }
            //If no target is set, we can't do anything
            return '';
        }

        //Otherwise we can return a label for the target
        return $this->elementTypeNameGenerator->formatLabelHTMLForEntity($target, $options['show_associated']);
    }
}
