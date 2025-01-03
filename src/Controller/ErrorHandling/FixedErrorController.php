<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Controller\ErrorHandling;

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ErrorController;

/**
 * This class decorates the default error decorator and changes the content type of responses, if it went through a
 * Turbo request.
 * The problem is, that the default error controller returns in the format of the preferred content type of the request.
 * This is turbo-stream. This causes Turbo to try to integrate it into the content frame and not trigger the ajax failed
 * events to show the error in a popup like intended.
 */
#[AsDecorator("error_controller")]
class FixedErrorController
{
    public function __construct(private readonly ErrorController $decorated)
    {}

    public function __invoke(\Throwable $exception): Response
    {
        $response = ($this->decorated)($exception);

        //Check the content type of the response
        $contentType = $response->headers->get('Content-Type');

        //If the content type is turbo stream, change the content type to html
        //This prevents Turbo to render the response as a turbo stream, and forces to render it in the popup
        if ($contentType === 'text/vnd.turbo-stream.html') {
            $response->headers->set('Content-Type', 'text/html');
        }

        return $response;
    }

    public function preview(Request $request, int $code): Response
    {
        return ($this->decorated)->preview($request, $code);
    }
}