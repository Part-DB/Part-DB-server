<?php

namespace App\Form\Filters\Constraints;

use App\DataTables\Filters\Constraints\InstanceOfConstraint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InstanceOfConstraintType extends AbstractType
{
    protected EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('data_class', InstanceOfConstraint::class);
    }

    public function getParent()
    {
        return ChoiceConstraintType::class;
    }
}