<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Controller\AdminPages;

use App\Entity\Attachments\CurrencyAttachment;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Parameters\CurrencyParameter;
use App\Entity\PriceInformations\Currency;
use App\Form\AdminPages\CurrencyAdminForm;
use App\Services\Attachments\AttachmentSubmitHandler;
use App\Services\ImportExportSystem\EntityExporter;
use App\Services\ImportExportSystem\EntityImporter;
use App\Services\Tools\ExchangeRateUpdater;
use App\Services\LabelSystem\LabelExampleElementsGenerator;
use App\Services\LabelSystem\LabelGenerator;
use App\Services\LogSystem\EventCommentHelper;
use App\Services\LogSystem\HistoryHelper;
use App\Services\LogSystem\TimeTravel;
use App\Services\Trees\StructuralElementRecursionHelper;
use Doctrine\ORM\EntityManagerInterface;
use Exchanger\Exception\ChainException;
use Exchanger\Exception\Exception;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/currency")
 *
 * Class CurrencyController
 */
class CurrencyController extends BaseAdminController
{
    protected string $entity_class = Currency::class;
    protected string $twig_template = 'admin/currency_admin.html.twig';
    protected string $form_class = CurrencyAdminForm::class;
    protected string $route_base = 'currency';
    protected string $attachment_class = CurrencyAttachment::class;
    protected ?string $parameter_class = CurrencyParameter::class;

    protected ExchangeRateUpdater $exchangeRateUpdater;

    public function __construct(
        TranslatorInterface $translator,
        UserPasswordHasherInterface $passwordEncoder,
        AttachmentSubmitHandler $attachmentSubmitHandler,
        EventCommentHelper $commentHelper,
        HistoryHelper $historyHelper,
        TimeTravel $timeTravel,
        DataTableFactory $dataTableFactory,
        EventDispatcherInterface $eventDispatcher,
        LabelExampleElementsGenerator $barcodeExampleGenerator,
        LabelGenerator $labelGenerator,
        EntityManagerInterface $entityManager,
        ExchangeRateUpdater $exchangeRateUpdater
    ) {
        $this->exchangeRateUpdater = $exchangeRateUpdater;

        parent::__construct(
            $translator,
            $passwordEncoder,
            $attachmentSubmitHandler,
            $commentHelper,
            $historyHelper,
            $timeTravel,
            $dataTableFactory,
            $eventDispatcher,
            $barcodeExampleGenerator,
            $labelGenerator,
            $entityManager
        );
    }

    /**
     * @Route("/{id}", name="currency_delete", methods={"DELETE"})
     */
    public function delete(Request $request, Currency $entity, StructuralElementRecursionHelper $recursionHelper): RedirectResponse
    {
        return $this->_delete($request, $entity, $recursionHelper);
    }

    public function additionalActionEdit(FormInterface $form, AbstractNamedDBElement $entity): bool
    {
        if (!$entity instanceof Currency) {
            return false;
        }

        //@phpstan-ignore-next-line
        if ($form->get('update_exchange_rate')->isClicked()) {
            $this->denyAccessUnlessGranted('edit', $entity);
            try {
                $this->exchangeRateUpdater->update($entity);
                $this->addFlash('info', 'currency.edit.exchange_rate_updated.success');
            } catch (Exception $exception) {
                //$exception = $exception->getExceptions()[0];
                if ($exception instanceof UnsupportedCurrencyPairException || false !== stripos($exception->getMessage(), 'supported')) {
                    $this->addFlash('error', 'currency.edit.exchange_rate_update.unsupported_currency');
                } else {
                    $this->addFlash('error', 'currency.edit.exchange_rate_update.generic_error');
                }
            }
        }

        return true;
    }

    /**
     * @Route("/{id}/edit/{timestamp}", requirements={"id"="\d+"}, name="currency_edit")
     * @Route("/{id}", requirements={"id"="\d+"})
     */
    public function edit(Currency $entity, Request $request, EntityManagerInterface $em, ?string $timestamp = null): Response
    {
        return $this->_edit($entity, $request, $em, $timestamp);
    }

    /**
     * @Route("/new", name="currency_new")
     * @Route("/{id}/clone", name="currency_clone")
     * @Route("/")
     */
    public function new(Request $request, EntityManagerInterface $em, EntityImporter $importer, ?Currency $entity = null): Response
    {
        return $this->_new($request, $em, $importer, $entity);
    }

    /**
     * @Route("/export", name="currency_export_all")
     */
    public function exportAll(EntityManagerInterface $em, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportAll($em, $exporter, $request);
    }

    /**
     * @Route("/{id}/export", name="currency_export")
     */
    public function exportEntity(Currency $entity, EntityExporter $exporter, Request $request): Response
    {
        return $this->_exportEntity($entity, $exporter, $request);
    }

    public function deleteCheck(AbstractNamedDBElement $entity): bool
    {
        if (($entity instanceof Currency) && $entity->getPricedetails()->count() > 0) {
            $this->addFlash('error', 'entity.delete.must_not_contain_prices');

            return false;
        }

        return true;
    }
}
