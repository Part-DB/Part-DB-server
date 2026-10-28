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

namespace App\Twig;

use App\Entity\LabelSystem\LabelProfile;
use App\Entity\UserSystem\User;
use App\Services\LabelSystem\AgentJobCreator;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

final class AgentExtension
{
    /** @var array<int, list<array{agent: \App\Entity\AgentSystem\Agent, id: string, name: string}>> Per request: label menus ask for every profile */
    private array $printers = [];

    public function __construct(private readonly AgentJobCreator $agentJobCreator, private readonly Security $security)
    {
    }

    /**
     * The printers of the current user's print agents.
     * @return list<array{agent: \App\Entity\AgentSystem\Agent, id: string, name: string}>
     */
    #[AsTwigFunction("agent_printers")]
    public function agentPrinters(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->security->isGranted('@labels.create_labels')) {
            return [];
        }
        return $this->printers[$user->getID()] ??= $this->agentJobCreator->printersFor($user);
    }

    /**
     * The profile's default printer, if one of the current user's print agents has it.
     * @return array{agent: \App\Entity\AgentSystem\Agent, id: string, name: string}|null
     */
    #[AsTwigFunction("agent_default_printer")]
    public function agentDefaultPrinter(LabelProfile $profile): ?array
    {
        if ($profile->getDefaultPrinter() === '') {
            return null;
        }
        foreach ($this->agentPrinters() as $printer) {
            if ($printer['id'] === $profile->getDefaultPrinter()) {
                return $printer;
            }
        }
        return null;
    }
}
