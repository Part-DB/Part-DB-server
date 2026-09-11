<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Catches timezone mismatches between a DateTimeInterface model value and the effective
 * model_timezone configured on the field.
 *
 * Doctrine's UTCDateTimeImmutableType always returns UTC DateTimeImmutable objects, so any
 * date/datetime field that omits `model_timezone: 'UTC'` will silently corrupt stored values
 * (the transformer treats the UTC instant as if it were in the user's local timezone).
 * This extension throws a \LogicException early so the mistake is caught at development time.
 */
class DateTimeModelTimezoneExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [DateTimeType::class, DateType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event) use ($options): void {
            $data = $event->getData();

            if (!$data instanceof \DateTimeInterface) {
                return;
            }

            // Resolve the effective model timezone: explicit option or the PHP default set at build time.
            // This mirrors what BaseDateTimeTransformer does in its constructor.
            $modelTimezone = $options['model_timezone'] ?? date_default_timezone_get();

            $dataOffset  = $data->getTimezone()->getOffset($data);
            $modelOffset = (new \DateTimeZone($modelTimezone))->getOffset($data);

            if ($dataOffset !== $modelOffset) {
                throw new \LogicException(sprintf(
                    'Form field "%s" received a %s with timezone "%s" (UTC offset %+d s), '
                    . 'but the effective model_timezone is "%s" (UTC offset %+d s). '
                    . 'Set the "model_timezone" option to match the timezone of your data source.',
                    $event->getForm()->getName(),
                    get_debug_type($data),
                    $data->getTimezone()->getName(),
                    $dataOffset,
                    $modelTimezone,
                    $modelOffset
                ));
            }
        });
    }
}
