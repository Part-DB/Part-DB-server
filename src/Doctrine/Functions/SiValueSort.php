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

namespace App\Doctrine\Functions;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Custom DQL function that extracts the first numeric value with an optional SI prefix
 * from a string and returns the scaled numeric value for sorting.
 *
 * Usage: SI_VALUE_SORT(part.name)
 *
 * This enables sorting parts by their physical value. For example, capacitors
 * named "100nF", "1uF", "10pF" will be sorted by actual value: 10pF < 100nF < 1uF.
 *
 * Supported SI prefixes: p (pico, 1e-12), n (nano, 1e-9), u/µ (micro, 1e-6),
 * m (milli, 1e-3), k/K (kilo, 1e3), M (mega, 1e6), G (giga, 1e9), T (tera, 1e12).
 *
 * Only matches numbers at the very beginning of the string (ignoring leading whitespace).
 * Names like "Crystal 20MHz" will NOT match since the number is not at the start.
 * Names without a recognizable numeric+prefix pattern return NULL and sort last.
 */
class SiValueSort extends FunctionNode
{
    private ?Node $field = null;

    /**
     * SI prefix multipliers. Used by the SQLite PHP callback.
     */
    private const SI_MULTIPLIERS = [
        'p' => 1e-12,
        'n' => 1e-9,
        'u' => 1e-6,
        'µ' => 1e-6,
        'm' => 1e-3,
        'k' => 1e3,
        'K' => 1e3,
        'M' => 1e6,
        'G' => 1e9,
        'T' => 1e12,
    ];

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->field = $parser->ArithmeticExpression();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        assert($this->field !== null, 'Field is not set');

        $platform = $sqlWalker->getConnection()->getDatabasePlatform();
        $rawField = $this->field->dispatch($sqlWalker);

        // Normalize comma decimal separator to dot for SQL platforms (European locale support)
        $fieldSql = "REPLACE({$rawField}, ',', '.')";

        if ($platform instanceof PostgreSQLPlatform) {
            return $this->getPostgreSQLSql($fieldSql);
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            return $this->getMySQLSql($fieldSql);
        }

        // SQLite: comma normalization is handled in the PHP callback
        $fieldSql = $rawField;

        if ($platform instanceof SQLitePlatform) {
            return "SI_VALUE({$fieldSql})";
        }

        // Fallback: return NULL (no SI sorting available)
        return 'NULL';
    }

    /**
     * PostgreSQL implementation using substring() with POSIX regex.
     */
    private function getPostgreSQLSql(string $field): string
    {
        // Extract the numeric part using POSIX regex, anchored at start (with optional leading whitespace)
        $numericPart = "CAST(substring({$field} FROM '^\\s*(\\d+\\.?\\d*)\\s*[pnuµmkKMGT]?') AS DOUBLE PRECISION)";

        // Extract the SI prefix character
        $prefixPart = "substring({$field} FROM '^\\s*\\d+\\.?\\d*\\s*([pnuµmkKMGT])')";

        return $this->buildCaseExpression($numericPart, $prefixPart);
    }

    /**
     * MySQL/MariaDB implementation using REGEXP_SUBSTR.
     */
    private function getMySQLSql(string $field): string
    {
        // Extract the numeric part, anchored at start (with optional leading whitespace)
        $numericPart = "CAST(REGEXP_SUBSTR({$field}, '^[[:space:]]*[0-9]+\\.?[0-9]*') AS DECIMAL(30,15))";

        // Extract the prefix: get the full number+prefix match anchored at start, then take the last char
        $fullMatch = "REGEXP_SUBSTR({$field}, '^[[:space:]]*[0-9]+\\.?[0-9]*[[:space:]]*[pnuµmkKMGT]')";
        $prefixPart = "RIGHT({$fullMatch}, 1)";

        return $this->buildCaseExpression($numericPart, $prefixPart);
    }

    /**
     * Build a CASE expression that maps an SI prefix character to a multiplier
     * and multiplies it with the numeric value.
     *
     * @param string $numericExpr SQL expression that evaluates to the numeric part
     * @param string $prefixExpr SQL expression that evaluates to the SI prefix character
     * @return string SQL CASE expression
     */
    private function buildCaseExpression(string $numericExpr, string $prefixExpr): string
    {
        return "(CASE" .
            " WHEN {$numericExpr} IS NULL THEN NULL" .
            " WHEN {$prefixExpr} = 'p' THEN {$numericExpr} * 1e-12" .
            " WHEN {$prefixExpr} = 'n' THEN {$numericExpr} * 1e-9" .
            " WHEN {$prefixExpr} = 'u' THEN {$numericExpr} * 1e-6" .
            " WHEN {$prefixExpr} = 'µ' THEN {$numericExpr} * 1e-6" .
            " WHEN {$prefixExpr} = 'm' THEN {$numericExpr} * 1e-3" .
            " WHEN {$prefixExpr} = 'k' THEN {$numericExpr} * 1e3" .
            " WHEN {$prefixExpr} = 'K' THEN {$numericExpr} * 1e3" .
            " WHEN {$prefixExpr} = 'M' THEN {$numericExpr} * 1e6" .
            " WHEN {$prefixExpr} = 'G' THEN {$numericExpr} * 1e9" .
            " WHEN {$prefixExpr} = 'T' THEN {$numericExpr} * 1e12" .
            " ELSE {$numericExpr} * 1" .
            " END)";
    }

    /**
     * PHP callback for SQLite's SI_VALUE function.
     * Extracts the first numeric value with an optional SI prefix and returns the scaled value.
     *
     * @param string|null $value The input string
     * @return float|null The scaled numeric value, or null if no number found
     */
    public static function sqliteSiValue(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        // Normalize comma decimal separator to dot (European locale support)
        $value = str_replace(',', '.', $value);

        // Match a number at the very start (allowing leading whitespace), optionally followed by an SI prefix
        if (!preg_match('/^\s*(\d+\.?\d*)\s*([pnuµmkKMGT])?/u', $value, $matches)) {
            return null;
        }

        $number = (float) $matches[1];
        $prefix = $matches[2] ?? '';

        if ($prefix === '') {
            return $number;
        }

        $multiplier = self::SI_MULTIPLIERS[$prefix] ?? 1.0; //@phpstan-ignore-line - fallback to 1.0 if prefix is not recognized (should not happen due to regex)

        return $number * $multiplier;
    }
}
