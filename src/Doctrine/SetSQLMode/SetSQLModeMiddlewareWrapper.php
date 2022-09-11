<?php

namespace App\Doctrine\SetSQLMode;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * This class wraps the Doctrine DBAL driver and wraps it into an Midleware driver so we can change the SQL mode
 */
class SetSQLModeMiddlewareWrapper implements Middleware
{

    public function wrap(Driver $driver): Driver
    {
        return new SetSQLModeMiddlewareDriver($driver);
    }
}