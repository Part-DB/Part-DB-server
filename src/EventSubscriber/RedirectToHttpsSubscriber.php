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


namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * The purpose of this event listener is (if enabled) to redirect all requests to https.
 */
final class RedirectToHttpsSubscriber implements EventSubscriberInterface
{

    public function __construct(
        #[Autowire('%env(bool:REDIRECT_TO_HTTPS)%')]
        private readonly bool $enabled,
        private readonly HttpUtils $httpUtils)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        //If the feature is disabled, or we are not the main request, we do nothing
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }


        $request = $event->getRequest();

        //If the request is already https, we do nothing
        if ($request->isSecure()) {
            return;
        }


        //Change the request to https
        $new_url = str_replace('http://', 'https://' ,$request->getUri());
        $event->setResponse($this->httpUtils->createRedirectResponse($event->getRequest(), $new_url));
    }
}