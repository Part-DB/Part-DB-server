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


namespace App\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\ORMInvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use ApiPlatform\State\ApiResource\Error;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * This class adds a custom error if the user tries to create a new entity through a relation, and suggests to do reference it through an IRI instead.
 * This class decorates the default error handler of API Platform.
 */
#[AsDecorator('api_platform.state.error_provider')]
final class ErrorHandler implements ProviderInterface
{
    public function __construct(private readonly ProviderInterface $decorated, #[Autowire('%kernel.debug%')] private readonly bool $debug)
    {

    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $context['request'];
        $format = $request->getRequestFormat();
        $exception = $request->attributes->get('exception');

        //Check if the exception is a ORM InvalidArgument exception and complains about a not-persisted entity through relation
        if ($exception instanceof ORMInvalidArgumentException && str_contains($exception->getMessage(), 'A new entity was found through the relationship')) {
            //Extract the entity class and property name from the exception message
            $matches = [];
            preg_match('/A new entity was found through the relationship \'(?<property>.*)\'/i', $exception->getMessage(), $matches);

            $property = $matches['property'] ?? "unknown";

            //Create a new error response
            $error = Error::createFromException($exception, 400);

            //Return the error response
            $detail = "You tried to create a new entity through the relation '$property', but this is not allowed. Please create the entity first and then reference it through an IRI!";
            //If we are in debug mode, add the exception message to the error response
            if ($this->debug) {
                $detail .= " Original exception message: " . $exception->getMessage();
            }
            $error->setDetail($detail);
            return $error;
        }


        return $this->decorated->provide($operation, $uriVariables, $context);
    }
}