<?php

namespace App\Services\LabelSystem\PlaceholderProviders;

use App\Entity\LabelSystem\LabelOptions;
use App\Entity\LabelSystem\LabelProfile;
use App\Services\LabelSystem\BarcodeGenerator;
use App\Services\LabelSystem\Barcodes\BarcodeContentGenerator;

final class BarcodeProvider implements PlaceholderProviderInterface
{
    private BarcodeGenerator $barcodeGenerator;
    private BarcodeContentGenerator $barcodeContentGenerator;

    public function __construct(BarcodeGenerator $barcodeGenerator, BarcodeContentGenerator $barcodeContentGenerator)
    {
        $this->barcodeGenerator = $barcodeGenerator;
        $this->barcodeContentGenerator = $barcodeContentGenerator;
    }

    public function replace(string $placeholder, object $label_target, array $options = []): ?string
    {
        if ('[[1D_CONTENT]]' === $placeholder) {
            try {
                return $this->barcodeContentGenerator->get1DBarcodeContent($label_target);
            } catch (\InvalidArgumentException $e) {
                return 'ERROR!';
            }
        }

        if ('[[2D_CONTENT]]' === $placeholder) {
            try {
                return $this->barcodeContentGenerator->getURLContent($label_target);
            } catch (\InvalidArgumentException $e) {
                return 'ERROR!';
            }
        }

        if ('[[BARCODE_QR]]' === $placeholder) {
            $label_options = new LabelOptions();
            $label_options->setBarcodeType('qr');
            return $this->barcodeGenerator->generateHTMLBarcode($label_options, $label_target);
        }

        if ('[[BARCODE_C39]]' === $placeholder) {
            $label_options = new LabelOptions();
            $label_options->setBarcodeType('code39');
            return $this->barcodeGenerator->generateHTMLBarcode($label_options, $label_target);
        }

        if ('[[BARCODE_C128]]' === $placeholder) {
            $label_options = new LabelOptions();
            $label_options->setBarcodeType('code128');
            return $this->barcodeGenerator->generateHTMLBarcode($label_options, $label_target);
        }

        return null;
    }
}