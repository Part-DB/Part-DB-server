<?php

namespace App\DataTables\Column;

use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A column representing the checkboxes for select extensions.
 */
class SelectColumn extends AbstractColumn
{
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'label' => '',
            'orderable' => false,
            'searchable' => false,
            'className' => 'select-checkbox no-colvis',
            'visible' => true,
        ]);
    }

    public function normalize($value)
    {
        return $value;
    }

    public function render($value, $context)
    {
        //Return empty string, as it this column is filled by datatables on client side
        return '';
    }
}