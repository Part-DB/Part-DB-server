<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 *
 */

namespace App\Form\Part;

use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\Storelocation;
use App\Form\AttachmentFormType;
use App\Form\AttachmentType;
use App\Form\Type\SIUnitType;
use App\Form\Type\StructuralEntityType;
use Doctrine\DBAL\Types\FloatType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class PartBaseType extends AbstractType
{
    protected $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var Part $part */
        $part = $builder->getData();

        //Common section
        $builder
            ->add('name', TextType::class, ['empty_data' => '', 'label' => 'name.label',
                'attr' => ['placeholder' => 'part.name.placeholder'],
                'disabled' => !$this->security->isGranted('name.edit', $part), ])
            ->add('description', CKEditorType::class, ['required' => false, 'empty_data' => '',
                'label' => 'description.label', 'help' => 'bbcode.hint', 'config_name' => 'description_config',
                'attr' => ['placeholder' => 'part.description.placeholder', 'rows' => 2],
                'disabled' => !$this->security->isGranted('description.edit', $part) ])
            ->add('minAmount', SIUnitType::class,
                ['attr' => ['min' => 0, 'placeholder' => 'part.mininstock.placeholder'], 'label' => 'mininstock.label',
                    'measurement_unit' => $part->getPartUnit(),
                    'disabled' => !$this->security->isGranted('mininstock.edit', $part), ])
            ->add('category', StructuralEntityType::class, ['class' => Category::class,
                'label' => 'category.label', 'disable_not_selectable' => true,
                'disabled' => !$this->security->isGranted('move', $part), ])
            ->add('footprint', StructuralEntityType::class, ['class' => Footprint::class, 'required' => false,
                'label' => 'footprint.label', 'disable_not_selectable' => true,
                'disabled' => !$this->security->isGranted('move', $part), ])
            ->add('tags', TextType::class, ['required' => false, 'label' => 'part.tags', 'empty_data' => "",
                'attr' => ['data-role' => 'tagsinput'],
                'disabled' => !$this->security->isGranted('edit', $part) ]);

        //Manufacturer section
        $builder->add('manufacturer', StructuralEntityType::class, ['class' => Manufacturer::class,
            'required' => false, 'label' => 'manufacturer.label', 'disable_not_selectable' => true,
            'disabled' => !$this->security->isGranted('manufacturer.edit', $part), ])
            ->add('manufacturer_product_url', UrlType::class, ['required' => false, 'empty_data' => '',
                'label' => 'manufacturer_url.label',
                'disabled' => !$this->security->isGranted('manufacturer.edit', $part), ])
            ->add('manufacturer_product_number', TextType::class, ['required' => false,
                'empty_data' => '', 'label' => 'part.mpn',
                'disabled' => !$this->security->isGranted('manufacturer.edit', $part)]);

        //Advanced section
        $builder->add('needsReview', CheckboxType::class, ['label_attr'=> ['class' => 'checkbox-custom'],
            'required' => false, 'label' => 'part.edit.needs_review'])
            ->add('favorite', CheckboxType::class, ['label_attr'=> ['class' => 'checkbox-custom'],
                'required' => false, 'label' => 'part.edit.is_favorite'])
            ->add('mass', SIUnitType::class, ['unit' => 'g',
                'label' => 'part.mass', 'required' => false])
            ->add('partUnit', StructuralEntityType::class, ['class'=> MeasurementUnit::class,
                'required' => false, 'disable_not_selectable' => true, 'label' => 'part.partUnit']);


        //Comment section
        $builder->add('comment', CKEditorType::class, ['required' => false,
            'label' => 'comment.label', 'attr' => ['rows' => 4], 'help' => 'bbcode.hint',
            'disabled' => !$this->security->isGranted('comment.edit', $part), 'empty_data' => '']);

        //Part Lots section
        $builder->add('partLots', CollectionType::class, [
            'entry_type' => PartLotType::class,
            'allow_add' => true, 'allow_delete' => true,
            'label' => false,
            'entry_options' => [
                'measurement_unit' => $part->getPartUnit()
            ],
            'by_reference' => false
        ]);

        //Attachment section
        $builder->add('attachments', CollectionType::class, [
            'entry_type' => AttachmentFormType::class,
            'allow_add' => true, 'allow_delete' => true,
            'label' => false,
            'entry_options' => [
                'data_class' => PartAttachment::class
            ],
            'by_reference' => false
        ]);

        //Attachment section
        $builder->add('orderDetails', CollectionType::class, [
            'entry_type' => OrderdetailType::class,
            'allow_add' => true, 'allow_delete' => true,
            'label' => false,
            'by_reference' => false,
        ]);

        $builder
            //Buttons
            ->add('save', SubmitType::class, ['label' => 'part.edit.save'])
            ->add('reset', ResetType::class, ['label' => 'part.edit.reset']);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Part::class,
        ]);
    }
}
