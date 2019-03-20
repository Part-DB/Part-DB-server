<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 *
 */

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DebugController
 * @package App\Controller
 */
class DebugController extends AbstractController
{
    /**
     * @Route("/debug/flash_test")
     */
    public function flashTest()
    {
        $this->addFlash('success', 'Success Flash Message!');
        $this->addFlash('error', 'Error Flash Message!');
        $this->addFlash('warning', 'Warning Flash Message!');
        $this->addFlash('notice', 'Notice Flash Message!');
        $this->addFlash('info', 'Info Flash Message! <b>Test</b>');

        $this->addFlash('testkjfd', 'Blabla. This message type should be not know to template!');

        return $this->render('base.html.twig');
    }
}