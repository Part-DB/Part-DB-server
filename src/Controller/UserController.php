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

namespace App\Controller;

use App\DataTables\LogDataTable;
use App\Entity\UserSystem\User;
use App\Form\Permissions\PermissionsType;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use InvalidArgumentException;

#[Route(path: '/user')]
class UserController extends AbstractController
{
    #[Route(path: '/info', name: 'user_info_self')]
    #[Route(path: '/{id}/info', name: 'user_info')]
    public function userInfo(?User $user, Packages $packages, Request $request, DataTableFactory $dataTableFactory): Response
    {
        //If no user id was passed, then we show info about the current user
        if (!$user instanceof User) {
            $tmp = $this->getUser();
            if (!$tmp instanceof User) {
                throw new InvalidArgumentException('Userinfo only works for database users!');
            }
            $user = $tmp;
        } else {
            //Else we must check, if the current user is allowed to access $user
            $this->denyAccessUnlessGranted('info', $user);
        }

        $table = null;
        //Only show the history table, if the user is the current user
        if ($user === $this->getUser()) {
            $table = $dataTableFactory->createFromType(
                LogDataTable::class,
                ['filter_elements' => $user, 'mode' => 'element_history'],
                ['pageLength' => 10]
            )->handleRequest($request);

            if ($table->isCallback()) {
                return $table->getResponse();
            }
        }

        $builder = $this->createFormBuilder()->add('permissions', PermissionsType::class, [
            'mapped' => false,
            'disabled' => true,
            'inherit' => true,
            'data' => $user,
        ]);

        return $this->render('users/user_info.html.twig', [
            'user' => $user,
            'form' => $builder->getForm(),
            'datatable' => $table,
        ]);
    }
}
