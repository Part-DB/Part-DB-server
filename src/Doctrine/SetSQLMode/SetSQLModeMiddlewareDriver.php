<?php

namespace App\Doctrine\SetSQLMode;

use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

/**
 * This command sets the initial command parameter for MySQL connections, so we can set the SQL mode
 * We use this to disable the ONLY_FULL_GROUP_BY mode, which is enabled by default in MySQL 5.7.5 and higher and causes problems with our filters
 */
class SetSQLModeMiddlewareDriver extends AbstractDriverMiddleware
{
    public function connect(array $params): \Doctrine\DBAL\Driver\Connection
    {
        //Only set this on MySQL connections, as other databases don't support this parameter
        if($this->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            //1002 is \PDO::MYSQL_ATTR_INIT_COMMAND constant value
            $params['driverOptions'][1002] = 'SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode, \'ONLY_FULL_GROUP_BY\', \'\'))';
        }

        return parent::connect($params);
    }
}