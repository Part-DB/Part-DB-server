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


namespace App\Doctrine\Functions;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Lexer;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use DoctrineExtensions\Query\Mysql\Field;

class CustomField extends Field
{

    protected Node|null|string $field = null;

    protected array $values = [];


    public function parse(\Doctrine\ORM\Query\Parser $parser): void
    {
        //If we are on MySQL, we can just call the parent method, as these values are not needed in that class then
        if ($parser->getEntityManager()->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            parent::parse($parser);
            return;
        }

        //Otherwise we have to do the same as the parent class, so we can use the same getSql method
        $parser->match(Lexer::T_IDENTIFIER);
        $parser->match(Lexer::T_OPEN_PARENTHESIS);

        // Do the field.
        $this->field = $parser->ArithmeticPrimary();

        // Add the strings to the values array. FIELD must
        // be used with at least 1 string not including the field.

        $lexer = $parser->getLexer();

        while (count($this->values) < 1 ||
            $lexer->lookahead['type'] != Lexer::T_CLOSE_PARENTHESIS) {
            $parser->match(Lexer::T_COMMA);
            $this->values[] = $parser->ArithmeticPrimary();
        }

        $parser->match(Lexer::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        //If we are on MySQL, we can use the builtin FIELD function and just call the parent method
        if ($sqlWalker->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return parent::getSql($sqlWalker);
        }

        //When we are on SQLite, we have to emulate it with the FIELD2 function
        if ($sqlWalker->getConnection()->getDatabasePlatform() instanceof SqlitePlatform) {
            return $this->getSqlForSQLite($sqlWalker);
        }

        throw new \LogicException('Unsupported database platform');
    }

    /**
     * Very similar to the parent method, but uses custom implmented FIELD2 function, which takes the values as a comma separated list
     * instead of an array to migigate the argument count limit of SQLite.
     * @param  SqlWalker  $sqlWalker
     * @return string
     * @throws \Doctrine\ORM\Query\AST\ASTException
     */
    private function getSqlForSQLite(SqlWalker $sqlWalker): string
    {
        $query = 'FIELD2(';

        $query .= $this->field->dispatch($sqlWalker);

        $query .= ', "';

        for ($i = 0, $iMax = count($this->values); $i < $iMax; $i++) {
            if ($i > 0) {
                $query .= ',';
            }

            $query .= $this->values[$i]->dispatch($sqlWalker);
        }

        $query .= '")';

        return $query;
    }
}