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

namespace App\DataFixtures;

use App\Entity\LabelSystem\LabelOptions;
use App\Entity\LabelSystem\LabelProfile;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class LabelProfileFixtures extends Fixture
{
    public function __construct(protected EntityManagerInterface $em)
    {
    }

    public function load(ObjectManager $manager): void
    {

        $profile1 = new LabelProfile();
        $profile1->setName('Profile 1');
        $profile1->setShowInDropdown(true);

        $option1 = new LabelOptions();
        $option1->setLines("[[NAME]]\n[[DESCRIPION]]");
        $option1->setBarcodeType('none');
        $option1->setSupportedElement('part');
        $profile1->setOptions($option1);

        $manager->persist($profile1);

        $profile2 = new LabelProfile();
        $profile2->setName('Profile 2');
        $profile2->setShowInDropdown(false);

        $option2 = new LabelOptions();
        $option2->setLines("[[NAME]]\n[[DESCRIPION]]");
        $option2->setBarcodeType('qr');
        $option2->setSupportedElement('part');
        $profile2->setOptions($option2);

        $manager->persist($profile2);

        $profile3 = new LabelProfile();
        $profile3->setName('Profile 3');
        $profile3->setShowInDropdown(true);

        $option3 = new LabelOptions();
        $option3->setLines("[[NAME]]\n[[DESCRIPION]]");
        $option3->setBarcodeType('code128');
        $option3->setSupportedElement('part_lot');
        $profile3->setOptions($option3);

        $manager->persist($profile3);

        $profile4 = new LabelProfile();
        $profile4->setName('Profile 4');
        $profile4->setShowInDropdown(true);

        $option4 = new LabelOptions();
        $option4->setLines('{{ element.name }}');
        $option4->setBarcodeType('code39');
        $option4->setSupportedElement('part');
        $option4->setProcessMode('twig');
        $profile4->setOptions($option4);

        $manager->persist($profile4);

        $manager->flush();
    }
}
