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

namespace App\Services\LogSystem;

use App\Entity\LogSystem\AbstractLogEntry;
use App\Services\ElementTypeNameGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LogDataFormatter
{
    private const STRING_MAX_LENGTH = 1024;

    private TranslatorInterface $translator;
    private EntityManagerInterface $entityManager;
    private ElementTypeNameGenerator $elementTypeNameGenerator;

    public function __construct(TranslatorInterface $translator, EntityManagerInterface $entityManager, ElementTypeNameGenerator $elementTypeNameGenerator)
    {
        $this->translator = $translator;
        $this->entityManager = $entityManager;
        $this->elementTypeNameGenerator = $elementTypeNameGenerator;
    }

    /**
     * Formats the given data of a log entry as HTML
     * @param mixed $data
     * @param  AbstractLogEntry  $logEntry
     * @param  string  $fieldName
     * @return string
     */
    public function formatData($data, AbstractLogEntry $logEntry, string $fieldName): string
    {
        if (is_string($data)) {
            $tmp = '<span class="text-muted user-select-none">"</span>' . mb_strimwidth(htmlspecialchars($data), 0, self::STRING_MAX_LENGTH, ) . '<span class="text-muted user-select-none">"</span>';

            //Show special characters and line breaks
            $tmp = preg_replace('/\n/', '<span class="text-muted user-select-none">\\n</span><br>', $tmp);
            $tmp = preg_replace('/\r/', '<span class="text-muted user-select-none">\\r</span>', $tmp);
            $tmp = preg_replace('/\t/', '<span class="text-muted user-select-none">\\t</span>', $tmp);

            return $tmp;
        }

        if (is_bool($data)) {
            return $this->formatBool($data);
        }

        if (is_int($data)) {
            return (string) $data;
        }

        if (is_float($data)) {
            return (string) $data;
        }

        if (is_null($data)) {
            return '<i>null</i>';
        }

        if (is_array($data)) {
            //If the array contains only one element with the key @id, it is a reference to another entity (foreign key)
            if (isset($data['@id'])) {
                return $this->formatForeignKey($data, $logEntry, $fieldName);
            }

            //If the array contains a "date", "timezone_type" and "timezone" key, it is a DateTime object
            if (isset($data['date'], $data['timezone_type'], $data['timezone'])) {
                return $this->formatDateTime($data);
            }


            return $this->formatJSON($data);
        }


        throw new \RuntimeException('Type of $data not supported (' . gettype($data) . ')');
    }

    private function formatJSON(array $data): string
    {
        $json = htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT), ENT_QUOTES | ENT_SUBSTITUTE);

        return sprintf(
            '<div data-controller="elements--json-formatter" data-json="%s"></div>',
            $json
        );
    }

    private function formatForeignKey(array $data, AbstractLogEntry $logEntry, string $fieldName): string
    {
        //Extract the id from the @id key
        $id = $data['@id'];

        try {
            //Retrieve the class type from the logEntry and retrieve the doctrine metadata
            $classMetadata = $this->entityManager->getClassMetadata($logEntry->getTargetClass());
            $fkTargetClass = $classMetadata->getAssociationTargetClass($fieldName);

            //Try to retrieve the entity from the database
            $entity = $this->entityManager->getRepository($fkTargetClass)->find($id);

            //If the entity was found, return a label for this entity
            if ($entity) {
                return $this->elementTypeNameGenerator->formatLabelHTMLForEntity($entity, true);
            } else { //Otherwise the entity was deleted, so return the id
                return $this->elementTypeNameGenerator->formatElementDeletedHTML($fkTargetClass, $id);
            }


        } catch (\InvalidArgumentException|\ReflectionException $exception) {
            return '<i>unknown target class</i>: ' . $id;
        }
    }

    private function formatDateTime(array $data): string
    {
        if (!isset($data['date'], $data['timezone_type'], $data['timezone'])) {
            return '<i>unknown DateTime format</i>';
        }

        $date = $data['date'];
        $timezoneType = $data['timezone_type'];
        $timezone = $data['timezone'];

        if (!is_string($date) || !is_int($timezoneType) || !is_string($timezone)) {
            return '<i>unknown DateTime format</i>';
        }

        try {
            $dateTime = new \DateTime($date, new \DateTimeZone($timezone));
        } catch (\Exception $exception) {
            return '<i>unknown DateTime format</i>';
        }

        //Format it to the users locale
        $formatter = new \IntlDateFormatter(null, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::MEDIUM);
        return $formatter->format($dateTime);
    }

    private function formatBool(bool $data): string
    {
        return $data ? $this->translator->trans('true') : $this->translator->trans('false');
    }
}