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

use App\Entity\UserSystem\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RedirectController extends AbstractController
{
    protected $default_locale;
    protected $translator;
    protected $session;

    public function __construct(string $default_locale, TranslatorInterface $translator, SessionInterface $session)
    {
        $this->default_locale = $default_locale;
        $this->session = $session;
        $this->translator = $translator;
    }

    public function addLocalePart(Request $request)
    {
        //By default we use the global default locale
        $locale = $this->default_locale;

        //Check if a user has set a preferred language setting:
        $user = $this->getUser();
        if (($user instanceof User) && !empty($user->getLanguage())) {
            $locale = $user->getLanguage();
        }

        //Check if the user needs to change the password. In that case redirect him to settings_page
        if ($user instanceof User && $user->isNeedPwChange()) {
            $this->session->getFlashBag()->add('warning', $this->translator->trans('flash.password_change_needed'));
            return $this->redirectToRoute('user_settings', ['_locale' => $locale]);
        }

        //$new_url = str_replace($request->getPathInfo(), '/' . $locale . $request->getPathInfo(), $request->getUri());
        $new_url = $request->getUriForPath('/' . $locale . $request->getPathInfo());
        return $this->redirect($new_url);
    }
}