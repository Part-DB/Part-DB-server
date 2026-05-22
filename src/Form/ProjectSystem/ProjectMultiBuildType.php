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


namespace App\Form\ProjectSystem;

use App\Services\InfoProviderSystem\ProviderRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use App\Entity\ProjectSystem\Project;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Psr\Log\LoggerInterface;

class ProjectMultiBuildType extends AbstractType
{
    private LoggerInterface $logger;
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->logger->info("LEN {len}", ['len'=>count($options['projects'])]);
        foreach($options['projects'] as $p)
        {
            $builder->add($p->getID() . '_project', NumberType::class, [
                'label' => $p->getName(),
                'required' => false,
            ],0);            
        }

        $builder->add('submit', SubmitType::class, [
            'label' => 'info_providers.search.submit',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'projects' => [],
        ]);
        $resolver->setAllowedTypes('projects', 'array');
    }
}
