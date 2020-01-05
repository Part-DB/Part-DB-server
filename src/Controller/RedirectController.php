<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
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
    protected $enforce_index_php;

    public function __construct(string $default_locale, TranslatorInterface $translator, SessionInterface $session, bool $enforce_index_php)
    {
        $this->default_locale = $default_locale;
        $this->session = $session;
        $this->translator = $translator;
        $this->enforce_index_php = $enforce_index_php;
    }

    /**
     * This function is called whenever a route was not matching the localized routes.
     * The purpose is to redirect the user to the localized version of the page.
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function addLocalePart(Request $request)
    {
        //By default we use the global default locale
        $locale = $this->default_locale;

        //Check if a user has set a preferred language setting:
        $user = $this->getUser();
        if (($user instanceof User) && ! empty($user->getLanguage())) {
            $locale = $user->getLanguage();
        }

        //$new_url = str_replace($request->getPathInfo(), '/' . $locale . $request->getPathInfo(), $request->getUri());
        $new_url = $request->getUriForPath('/'.$locale.$request->getPathInfo());

        //If either mod_rewrite is not enabled or the index.php version is enforced, add index.php to the string
        if (($this->enforce_index_php || ! $this->checkIfModRewriteAvailable())
            && false === strpos($new_url, 'index.php')) {
            //Like Request::getUriForPath only with index.php
            $new_url = $request->getSchemeAndHttpHost().$request->getBaseUrl().'/index.php/'.$locale.$request->getPathInfo();
        }

        return $this->redirect($new_url);
    }

    /**
     * Check if mod_rewrite is available (URL rewriting is possible).
     * If this is true, we can redirect to /en, otherwise we have to redirect to index.php/en.
     * When the PHP is not used via Apache SAPI, we just assume that URL rewriting is available.
     *
     * @return bool
     */
    public function checkIfModRewriteAvailable()
    {
        if (! \function_exists('apache_get_modules')) {
            //If we can not check for apache modules, we just hope for the best and assume url rewriting is available
            //If you want to enforce index.php versions of the url, you can override this via ENV vars.
            return true;
        }

        //Check if the mod_rewrite module is loaded
        return \in_array('mod_rewrite', apache_get_modules(), false);
    }
}
