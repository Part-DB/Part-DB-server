<?php

namespace App\DataTables\Column;

use App\Services\SIFormatter;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SIUnitNumberColumn extends AbstractColumn
{
    protected $formatter;

    public function __construct(SIFormatter $formatter)
    {
        $this->formatter = $formatter;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefault('precision', 2);
        $resolver->setDefault('unit', '');
    }

    public function normalize($value)
    {
        //Ignore null values
        if ($value === null) {
            return '';
        }

        return $this->formatter->format((float) $value, $this->options['unit'], $this->options['precision']);
    }
}