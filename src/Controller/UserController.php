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


use App\Entity\User;
use App\Form\UserSettingsType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{

    /**
     * @Route("/user/info", name="user_info_self")
     * @Route("/user/{id}/info")
     */
    public function userInfo(?User $user, Packages $packages)
    {

        //If no user id was passed, then we show info about the current user
        if($user == null) {
            $user = $this->getUser();
        }

        if($this->getParameter("use_gravatar")) {
            $avatar = $this->getGravatar($user->getEmail(), 200, 'identicon');
        } else {
            $avatar = $packages->getUrl("/img/default_avatar.png");
        }


        return $this->render('Users/user_info.html.twig', [
                'user' => $user,
                'avatar' => $avatar
            ]);
    }

    /**
     * @Route("/user/settings", name="user_settings")
     */
    public function userSettings(Request $request, EntityManagerInterface $em)
    {
        $user = $this->getUser();

        //When user change its settings, he should be logged  in fully.
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');


        $form = $this->createForm(UserSettingsType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'user.settings.saved_flash');
        }

        return $this->render('Users/user_settings.html.twig', [
            "settings_form" => $form->createView()
        ]);
    }


    /**
     * Get either a Gravatar URL or complete image tag for a specified email address.
     *
     * @param string $email The email address
     * @param string $s Size in pixels, defaults to 80px [ 1 - 2048 ]
     * @param string $d Default imageset to use [ 404 | mm | identicon | monsterid | wavatar ]
     * @param string $r Maximum rating (inclusive) [ g | pg | r | x ]
     * @param bool $img True to return a complete IMG tag False for just the URL
     * @param array $atts Optional, additional key/value attributes to include in the IMG tag
     * @return String containing either just a URL or a complete image tag
     * @source https://gravatar.com/site/implement/images/php/
     */
    public function getGravatar(string $email, int $s = 80, string $d = 'mm', string $r = 'g', bool $img = false, array $atts = array())
    {
        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=$s&d=$d&r=$r";
        if ($img) {
            $url = '<img src="' . $url . '"';
            foreach ($atts as $key => $val) {
                $url .= ' ' . $key . '="' . $val . '"';
            }
            $url .= ' />';
        }
        return $url;
    }

}