<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
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
 */

namespace App\Form\Part;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\PriceInformations\Orderdetail;
use App\Form\AttachmentFormType;
use App\Form\Type\MasterPictureAttachmentType;
use App\Form\Type\SIUnitType;
use App\Form\Type\StructuralEntityType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;

class PartBaseType extends AbstractType
{
    protected $security;
    protected $trans;
    protected $urlGenerator;

    public function __construct(Security $security, UrlGeneratorInterface $urlGenerator)
    {
        $this->security = $security;
        $this->urlGenerator = $urlGenerator;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Part $part */
        $part = $builder->getData();

        $status_choices = [
            'm_status.unknown' => '',
            'm_status.announced' => 'announced',
            'm_status.active' => 'active',
            'm_status.nrfnd' => 'nrfnd',
            'm_status.eol' => 'eol',
            'm_status.discontinued' => 'discontinued',
        ];

        //Common section
        $builder
            ->add('name', TextType::class, [
                'empty_data' => '',
                'label' => 'part.edit.name',
                'attr' => [
                    'placeholder' => 'part.edit.name.placeholder',
                ],
                'disabled' => ! $this->security->isGranted('name.edit', $part),
            ])
            ->add('description', CKEditorType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'part.edit.description',
                'config_name' => 'description_config',
                'attr' => [
                    'placeholder' => 'part.edit.description.placeholder',
                    'rows' => 2,
                ],
                'disabled' => ! $this->security->isGranted('description.edit', $part),
            ])
            ->add('minAmount', SIUnitType::class, [
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'part.editmininstock.placeholder',
                ],
                'label' => 'part.edit.mininstock',
                'measurement_unit' => $part->getPartUnit(),
                'disabled' => ! $this->security->isGranted('minamount.edit', $part),
            ])
            ->add('category', StructuralEntityType::class, [
                'class' => Category::class,
                'label' => 'part.edit.category',
                'disable_not_selectable' => true,
                'disabled' => ! $this->security->isGranted('category.edit', $part),
            ])
            ->add('footprint', StructuralEntityType::class, [
                'class' => Footprint::class,
                'required' => false,
                'label' => 'part.edit.footprint',
                'disable_not_selectable' => true,
                'disabled' => ! $this->security->isGranted('footprint.edit', $part),
            ])
            ->add('tags', TextType::class, [
                'required' => false,
                'label' => 'part.edit.tags',
                'empty_data' => '',
                'attr' => [
                    'class' => 'tagsinput',
                    'data-autocomplete' => $this->urlGenerator->generate('typeahead_tags', ['query' => 'QUERY']),
                ],
                'disabled' => ! $this->security->isGranted('tags.edit', $part),
            ]);

        //Manufacturer section
        $builder->add('manufacturer', StructuralEntityType::class, [
            'class' => Manufacturer::class,
            'required' => false,
            'label' => 'part.edit.manufacturer.label',
            'disable_not_selectable' => true,
            'disabled' => ! $this->security->isGranted('manufacturer.edit', $part),
        ])
            ->add('manufacturer_product_url', UrlType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'part.edit.manufacturer_url.label',
                'disabled' => ! $this->security->isGranted('mpn.edit', $part),
            ])
            ->add('manufacturer_product_number', TextType::class, [
                'required' => false,
                'empty_data' => '',
                'label' => 'part.edit.mpn',
                'disabled' => ! $this->security->isGranted('mpn.edit', $part),
            ])
            ->add('manufacturing_status', ChoiceType::class, [
                'label' => 'part.edit.manufacturing_status',
                'choices' => $status_choices,
                'required' => false,
                'disabled' => ! $this->security->isGranted('status.edit', $part),
            ]);

        //Advanced section
        $builder->add('needsReview', CheckboxType::class, [
            'label_attr' => [
                'class' => 'checkbox-custom',
            ],
            'required' => false,
            'label' => 'part.edit.needs_review',
            'disabled' => ! $this->security->isGranted('edit', $part),
        ])
            ->add('favorite', CheckboxType::class, [
                'label_attr' => [
                    'class' => 'checkbox-custom',
                ],
                'required' => false,
                'label' => 'part.edit.is_favorite',
                'disabled' => ! $this->security->isGranted('change_favorite', $part),
            ])
            ->add('mass', SIUnitType::class, [
                'unit' => 'g',
                'label' => 'part.edit.mass',
                'required' => false,
                'disabled' => ! $this->security->isGranted('mass.edit', $part),
            ])
            ->add('partUnit', StructuralEntityType::class, [
                'class' => MeasurementUnit::class,
                'required' => false,
                'disable_not_selectable' => true,
                'label' => 'part.edit.partUnit',
                'disabled' => ! $this->security->isGranted('unit.edit', $part),
            ]);

        //Comment section
        $builder->add('comment', CKEditorType::class, [
            'required' => false,
            'label' => 'part.edit.comment',
            'attr' => [
                'rows' => 4,
            ],
            'disabled' => ! $this->security->isGranted('comment.edit', $part),
            'empty_data' => '',
        ]);

        //Part Lots section
        $builder->add('partLots', CollectionType::class, [
            'entry_type' => PartLotType::class,
            'allow_add' => $this->security->isGranted('lots.create', $part),
            'allow_delete' => $this->security->isGranted('lots.delete', $part),
            'label' => false,
            'entry_options' => [
                'measurement_unit' => $part->getPartUnit(),
                'disabled' => ! $this->security->isGranted('lots.edit', $part),
            ],
            'by_reference' => false,
        ]);

        //Attachment section
        $builder->add('attachments', CollectionType::class, [
            'entry_type' => AttachmentFormType::class,
            'allow_add' => $this->security->isGranted('attachments.create', $part),
            'allow_delete' => $this->security->isGranted('attachments.delete', $part),
            'label' => false,
            'entry_options' => [
                'data_class' => PartAttachment::class,
                'disabled' => ! $this->security->isGranted('attachments.edit', $part),
            ],
            'by_reference' => false,
        ]);

        $builder->add('master_picture_attachment', MasterPictureAttachmentType::class, [
            'required' => false,
            'disabled' => ! $this->security->isGranted('attachments.edit', $part),
            'label' => 'part.edit.master_attachment',
            'entity' => $part,
        ]);

        //Orderdetails section
        $builder->add('orderdetails', CollectionType::class, [
            'entry_type' => OrderdetailType::class,
            'allow_add' => $this->security->isGranted('orderdetails.create', $part),
            'allow_delete' => $this->security->isGranted('orderdetails.delete', $part),
            'label' => false,
            'by_reference' => false,
            'prototype_data' => new Orderdetail(),
            'entry_options' => [
                'measurement_unit' => $part->getPartUnit(),
                'disabled' => ! $this->security->isGranted('orderdetails.edit', $part),
            ],
        ]);

        $builder
            //Buttons
            ->add('save', SubmitType::class, ['label' => 'part.edit.save'])
            ->add('reset', ResetType::class, ['label' => 'part.edit.reset']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Part::class,
        ]);
    }
}
