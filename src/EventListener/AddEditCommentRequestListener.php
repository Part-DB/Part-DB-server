<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\EventListener;

use App\Services\LogSystem\EventCommentHelper;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener]
class AddEditCommentRequestListener
{
    public function __construct(private readonly EventCommentHelper $helper)
    {

    }

    public function __invoke(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();

        //Do not add comment if the request is a GET request
        if ($request->isMethod('GET')) {
            return;
        }

        //Check if the user tries to access a /api/ endpoint, if not skip
        if (!str_contains($request->getPathInfo(), '/api/')) {
            return;
        }

        //Extract the comment from the query parameter
        $comment = $request->query->getString('_comment', '');

        if ($comment !== '') {
            $this->helper->setMessage($comment);
        }
    }
}