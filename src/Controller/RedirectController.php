<?php
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

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UserSystem\User;
use function function_exists;
use function in_array;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class RedirectController extends AbstractController
{
    public function __construct(protected string $default_locale, protected TranslatorInterface $translator, protected bool $enforce_index_php)
    {
    }

    /**
     * This function is called whenever a route was not matching the localized routes.
     * The purpose is to redirect the user to the localized version of the page.
     */
    public function addLocalePart(Request $request): RedirectResponse
    {
        //By default, we use the global default locale
        $locale = $this->default_locale;

        //Check if a user has set a preferred language setting:
        $user = $this->getUser();
        if (($user instanceof User) && !empty($user->getLanguage())) {
            $locale = $user->getLanguage();
        }

        $new_url = $request->getUriForPath('/'.$locale.$request->getPathInfo());

        //If either mod_rewrite is not enabled or the index.php version is enforced, add index.php to the string
        if (($this->enforce_index_php || !$this->checkIfModRewriteAvailable())
            && !str_contains($new_url, 'index.php')) {
            //Like Request::getUriForPath only with index.php
            $new_url = $request->getSchemeAndHttpHost().$request->getBaseUrl().'/index.php/'.$locale.$request->getPathInfo();
        }

        //Add the query string
        $new_url .= $request->getQueryString() ? '?'.$request->getQueryString() : '';

        return $this->redirect($new_url);
    }

    /**
     * Check if mod_rewrite is available (URL rewriting is possible).
     * If this is true, we can redirect to /en, otherwise we have to redirect to index.php/en.
     * When the PHP is not used via Apache SAPI, we just assume that URL rewriting is available.
     */
    public function checkIfModRewriteAvailable(): bool
    {
        if (!function_exists('apache_get_modules')) {
            //If we can not check for apache modules, we just hope for the best and assume url rewriting is available
            //If you want to enforce index.php versions of the url, you can override this via ENV vars.
            return true;
        }

        //Check if the mod_rewrite module is loaded
        return in_array('mod_rewrite', apache_get_modules(), false);
    }
}
