<?php

namespace App\DataTables\Column;

use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Contracts\Translation\TranslatorInterface;

class PrettyBoolColumn extends AbstractColumn
{
    protected TranslatorInterface $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public function normalize($value): ?bool
    {
        if (null === $value) {
            return null;
        }

        return (bool) $value;
    }

    public function render($value, $context)
    {
        if ($value === true) {
            return '<span class="badge bg-success"><i class="fa-solid fa-circle-check fa-fw"></i> '
                . $this->translator->trans('bool.true')
                . '</span>';
        }

        if ($value === false) {
            return '<span class="badge bg-danger"><i class="fa-solid fa-circle-xmark fa-fw"></i> '
                . $this->translator->trans('bool.false')
                . '</span>';
        }

        if ($value === null) {
            return '<span class="badge bg-secondary>"<i class="fa-solid fa-circle-question fa-fw"></i> '
                . $this->translator->trans('bool.unknown')
                . '</span>';
        }

        throw new \RuntimeException('Unexpected value!');
    }
}