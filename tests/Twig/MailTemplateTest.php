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

namespace App\Tests\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

final class MailTemplateTest extends KernelTestCase
{
    public function testBaseTemplateLinksIgnoreForgedHost(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        //Simulate a request with a forged Host header
        $container->get(RouterInterface::class)->getContext()->setHost('evil-attacker.example');

        $html = $container->get(Environment::class)->render('mail/base.html.twig');

        $this->assertStringNotContainsString('evil-attacker.example', $html);
        $this->assertStringContainsString('href="'.rtrim($container->getParameter('partdb.default_uri'), '/').'/en/"', $html);
    }
}
