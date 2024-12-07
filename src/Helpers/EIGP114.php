<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2024 Alex Barclay (https://github.com/barclaac)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace App\Helpers;

use Psr\Log\LoggerInterface;

/*
  EIGP 114
  ISO/IEC 15418
  ANS MH10.8.2-2016

  Based on the ISO standard but it seems that both Mouser and Digikey aren't quite
  following the standard.

  Message is defined as (very loose RE format): ({:RS:} = 0x1e, {:GS:} = 0x1d, {:EOT:} = 0x04)
  Header: [)>{:RS:}06{:GS:}
  Field data identifier: [0-9]*[A-Z]
  Field data: .*
  Field Separator: {:GS:} - may or may not be present after last field
  Trailer: {:RS:}{:EOT:}

  Mouser & Digikey differences:
  Header: [)>06{:GS:} - note the missing {:RS:}
  Field: last field missing trailer {:RS:}{:EOT:} so use {:CR:} inserted by scanner itself

 */
class EIGP114
{
    public static function decode($barcode): array {
        $rslt = [];

        // Find the start sequence for a type 6 ISO/IEC 15434 message
        $hdrPattern = "/(\[\)>\u{001e}*06\u{001d})([[:print:]\u{001d}]*)(\u{001e}\u{0004})*/";
        $matches = [];
        $loc = preg_match_all($hdrPattern, $barcode, $matches);
        $fields = [];
        if ($loc) {
            $remain = $matches[2][0];
            $fields = preg_split("/\u{001d}/", $remain);
            foreach ($fields as &$v) {
                EIGP114::processField($v, $rslt);
            }
        }
        return $rslt;
    }

    private static function processField($field, &$result) : void {
        $pattern = "/([0-9]{0,2}[A-Z])(.+)/";
        $matches = [];
        if (preg_match_all($pattern, $field, $matches)) {
            switch ($matches[1][0]) {
            case 'L':
                $result['location'] = $matches[2][0];
                break;
            case '1P':
                $result['supplier_pn'] = $matches[2][0];
                break;
            case 'Q':
                $result['quantity'] = $matches[2][0];
                break;
            default:
                //array_push($result, $matches);
            }
        }
    }
}
