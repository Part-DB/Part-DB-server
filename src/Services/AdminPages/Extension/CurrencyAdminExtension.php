<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\AdminPages\Extension;

use App\Entity\PriceInformations\Currency;
use App\Services\AdminPages\AbstractAdminPageExtension;
use App\Services\Tools\ExchangeRateUpdater;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class CurrencyAdminExtension extends AbstractAdminPageExtension
{
    public function __construct(
        private readonly ExchangeRateUpdater $exchangeRateUpdater,
        private readonly AuthorizationCheckerInterface $authChecker,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function onEditFormValid(FormInterface $form, object $entity): bool
    {
        if (!$entity instanceof Currency) {
            throw new \InvalidArgumentException('Expected Currency, got ' . $entity::class . '.');
        }

        //@phpstan-ignore-next-line
        if ($form->get('update_exchange_rate')->isClicked()) {
            if (!$this->authChecker->isGranted('edit', $entity)) {
                throw new AccessDeniedException();
            }
            /** @var Session $session */
            $session = $this->requestStack->getSession();
            try {
                $this->exchangeRateUpdater->update($entity);
                $session->getFlashBag()->add('info', 'currency.edit.exchange_rate_updated.success');
            } catch (\Exception $e) {
                if ($e instanceof UnsupportedCurrencyPairException || str_contains(strtolower($e->getMessage()), 'supported')) {
                    $session->getFlashBag()->add('error', 'currency.edit.exchange_rate_update.unsupported_currency');
                } else {
                    $session->getFlashBag()->add('error', 'currency.edit.exchange_rate_update.generic_error');
                }
            }
        }

        return true;
    }

    public function checkDelete(object $entity): bool
    {
        if (!$entity instanceof Currency) {
            throw new \InvalidArgumentException('Expected Currency, got ' . $entity::class . '.');
        }

        if ($entity->getPricedetails()->count() > 0) {
            /** @var Session $session */
            $session = $this->requestStack->getSession();
            $session->getFlashBag()->add('error', 'entity.delete.must_not_contain_prices');

            return false;
        }

        return true;
    }
}
